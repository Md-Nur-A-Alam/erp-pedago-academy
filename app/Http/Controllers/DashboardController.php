<?php

namespace App\Http\Controllers;

use App\Actions\DistributeLeads;
use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use App\Models\LeadFollowup;
use App\Models\LeadSource;
use App\Models\LeadTransfer;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Lead statuses in the order they are shown in the pipeline.
     */
    public const PIPELINE_ORDER = [
        'New', 'Assigned', 'Contacted', 'Follow-up', 'Interested', 'Enrolled - Pending Payment',
        'Payment Under Review', 'Converted', 'Not Interested', 'Unreachable', 'Lost',
    ];

    private const OFF_DUTY_STATES = ['Off Duty', 'Holiday', 'Unavailable', 'Resigned'];

    public function index(Request $request, DistributeLeads $engine): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->employee instanceof Employee, 403);

        if ($user->employee->role === 'Developer') {
            return redirect()->route('developer.index');
        }

        $period = $this->reportingPeriod($request);
        $employee = $user->employee;
        $data = [
            'employee' => $employee,
            'range' => $period['range'],
            'rangeStart' => $period['start'],
            'rangeEnd' => $period['end'],
            'rangeLabel' => $period['label'],
        ];

        $data += match ($employee->role) {
            'Admin' => $this->adminData($engine, $period),
            'Sales Executive' => ['panel' => 'executive'] + $this->executiveData($employee),
            'Team Leader', 'Supervisor' => ['panel' => 'team'] + $this->teamData($employee, $engine),
            'MarketingExecutive' => ['panel' => 'marketing'] + $this->marketingData($period),
            default => ['panel' => null],
        };

        return view('dashboard', $data);
    }

    /**
     * Live operational figures ignore the reporting period; only lead totals and the pipeline follow it.
     *
     * @param  array{range: string, start: Carbon, end: Carbon, label: string}  $period
     * @return array<string, mixed>
     */
    private function adminData(DistributeLeads $engine, array $period): array
    {
        $leadsInRange = Lead::query()->whereBetween('created_at', [$period['start'], $period['end']]);
        $executives = $engine->executivesWithAvailability();
        $available = $engine->availableExecutives($executives);
        $lazyThreshold = SystemSetting::integer('lazy_threshold_hours');
        $autoApproveDays = SystemSetting::integer('transfer_auto_approve_days');
        $reminderDays = SystemSetting::integer('installment_reminder_days');
        $todayRange = [now()->startOfDay(), now()->endOfDay()];

        $pendingPayments = Payment::query()->where('status', 'Pending');
        $pendingTransfers = LeadTransfer::query()->where('status', 'Pending');
        $verifiedToday = Payment::query()->where('status', 'Verified')->whereBetween('verified_date', $todayRange);
        $oldestTransfer = (clone $pendingTransfers)->oldest()->first();

        $roster = $this->rosterRows($executives);

        return [
            'metrics' => [
                'total_leads' => (clone $leadsInRange)->count(),
                'unassigned_leads' => $engine->queueQuery()->count(),
                'available_executives' => $available->count(),
                'active_batches' => LeadBatch::query()->where('status', 'Active')->count(),
                'on_duty' => $executives->reject(
                    fn (Employee $executive): bool => in_array($executive->availability, self::OFF_DUTY_STATES, true),
                )->count(),
                'overdue_followups' => LeadFollowup::query()
                    ->where('is_done', false)
                    ->where('followup_date_time', '<', now())
                    ->count(),
                'lazy_executives' => LeadFollowup::query()
                    ->where('is_done', false)
                    ->where('followup_date_time', '<', now()->subHours($lazyThreshold))
                    ->distinct()
                    ->count('employee_id'),
                'payments_pending' => (clone $pendingPayments)->count(),
                'oldest_payment_at' => (clone $pendingPayments)->min('created_at'),
                'transfers_pending' => (clone $pendingTransfers)->count(),
                'next_auto_transfer_at' => $oldestTransfer?->created_at?->copy()->addDays($autoApproveDays),
                'converted_today' => (clone $verifiedToday)->distinct()->count('enrollment_id'),
                'collected_today' => (float) (clone $verifiedToday)->sum('amount'),
                'duplicate_reviews' => DuplicateLeadReview::query()->where('status', 'Pending Review')->count(),
            ],
            'pipeline' => (clone $leadsInRange)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'pipelineOrder' => self::PIPELINE_ORDER,
            'plan' => $engine->plan($available),
            'engineEnabled' => SystemSetting::enabled('distribution_engine_enabled'),
            'roster' => $roster->sortBy(fn (array $row): int => in_array($row['status'], self::OFF_DUTY_STATES, true) ? 1 : 0)->take(8)->values(),
            'rosterTotal' => $roster->count(),
            'attention' => [
                'payments' => (clone $pendingPayments)
                    ->with(['installmentSchedule:id,installment_number', 'enrollment:id,student_name'])
                    ->oldest()
                    ->limit(3)
                    ->get(),
                'transfers' => (clone $pendingTransfers)
                    ->with(['fromTeam:id,name', 'toTeam:id,name', 'lead:id,student_name'])
                    ->oldest()
                    ->limit(3)
                    ->get(),
                'installments' => InstallmentController::scoped('due_soon', $reminderDays)
                    ->with('enrollment:id,student_name')
                    ->orderBy('due_date')
                    ->limit(3)
                    ->get(),
            ],
            'autoApproveDays' => $autoApproveDays,
            'reminderDays' => $reminderDays,
            'pendingChanges' => EmployeeChangeRequest::query()->where('status', 'Pending')->count(),
        ];
    }

    /**
     * One row per executive for a roster table: team, shift, status, batch progress and overdue count.
     *
     * @param  Collection<int, Employee>  $executives  Loaded by DistributeLeads::executivesWithAvailability()
     * @return Collection<int, array{employee: Employee, team: ?string, shift: ?string, status: string, batch_total: int, batch_done: int, overdue: int}>
     */
    private function rosterRows(Collection $executives): Collection
    {
        return $executives->map(function (Employee $executive): array {
            $items = $executive->batches->flatMap->items;
            $shift = $executive->effectiveShift();

            return [
                'employee' => $executive,
                'team' => $executive->effectiveTeam()?->name,
                'shift' => $shift === null ? null : substr($shift->shift_start_time, 0, 5).'–'.substr($shift->shift_end_time, 0, 5),
                'status' => $executive->availability,
                'batch_total' => $items->count(),
                'batch_done' => $items->where('is_completed', true)->count(),
                'overdue' => $executive->leadFollowups->filter(
                    fn (LeadFollowup $followUp): bool => $followUp->followup_date_time->isPast(),
                )->count(),
            ];
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function executiveData(Employee $executive): array
    {
        $openItems = LeadBatchItem::query()
            ->whereHas('batch', fn ($batch) => $batch->where('employee_id', $executive->id)->where('status', 'Active'));
        $pendingFollowUps = LeadFollowup::query()->where('employee_id', $executive->id)->where('is_done', false);
        $shift = $executive->effectiveShift();

        return [
            'availability' => $executive->effectiveAvailabilityStatus(),
            'shiftLabel' => $shift === null ? null : substr($shift->shift_start_time, 0, 5).'–'.substr($shift->shift_end_time, 0, 5),
            'metrics' => [
                'batch_total' => (clone $openItems)->count(),
                'batch_done' => (clone $openItems)->where('is_completed', true)->count(),
                'due_today' => (clone $pendingFollowUps)->whereBetween('followup_date_time', [now(), now()->endOfDay()])->count(),
                'overdue' => (clone $pendingFollowUps)->where('followup_date_time', '<', now())->count(),
                'open_leads' => $executive->ownedLeads()->whereIn('status', ['Assigned', 'Contacted', 'Follow-up', 'Interested'])->count(),
                'awaiting_payment' => $executive->ownedLeads()->whereIn('status', ['Enrolled - Pending Payment', 'Payment Under Review'])->count(),
                'converted' => $executive->ownedLeads()->where('status', 'Converted')->count(),
            ],
            'nextLeads' => (clone $openItems)->where('is_completed', false)
                ->with(['lead:id,source_id,student_name,contact_number,status,created_at', 'lead.source:source_id,channel'])
                ->get()
                ->filter(fn (LeadBatchItem $item): bool => $item->lead !== null)
                ->sortBy(fn (LeadBatchItem $item): array => [$item->lead->priorityRank(), -$item->lead->created_at->getTimestamp()])
                ->take(5)
                ->values(),
            'nextFollowUps' => (clone $pendingFollowUps)->with('lead:id,student_name,contact_number')->orderBy('followup_date_time')->limit(5)->get(),
        ];
    }

    /**
     * Figures for a team leader (their team) or a supervisor (every team they supervise).
     *
     * @return array<string, mixed>
     */
    private function teamData(Employee $manager, DistributeLeads $engine): array
    {
        $teamIds = $manager->managedTeamIds();
        $executiveIds = $manager->managedExecutiveIds() ?? [];
        $roster = $this->rosterRows($engine->executivesWithAvailability()->whereIn('id', $executiveIds))
            ->sortBy(fn (array $row): array => [
                $row['status'] === 'Lazy' ? 0 : 1,
                -$row['overdue'],
                -($row['batch_total'] - $row['batch_done']),
                $row['employee']->name,
            ])
            ->values();
        $monthStart = now()->startOfMonth();
        $lazyThreshold = SystemSetting::integer('lazy_threshold_hours');
        $pendingFollowUps = LeadFollowup::query()->whereIn('employee_id', $executiveIds)->where('is_done', false);
        $assignedThisMonth = fn ($leads) => $leads->where('assigned_at', '>=', $monthStart);

        return [
            'teams' => Team::query()
                ->whereIn('id', $teamIds)
                ->with('teamLeader:id,name')
                ->withCount([
                    'members as members_count' => fn ($members) => $members->where('status', 'Active')->where('role', 'Sales Executive'),
                    'leads as assigned_count' => $assignedThisMonth,
                    'leads as converted_count' => fn ($leads) => $leads->where('assigned_at', '>=', $monthStart)->where('status', 'Converted'),
                    'leads as open_count' => fn ($leads) => $leads->whereIn('status', ['Assigned', 'Contacted', 'Follow-up', 'Interested']),
                ])
                ->orderBy('name')
                ->get(),
            'roster' => $roster,
            'uncalled' => Lead::query()
                ->whereIn('current_owner_id', $executiveIds)
                ->where('status', 'Assigned')
                ->whereNull('last_action_at')
                ->with(['currentOwner:id,name', 'source:source_id,channel'])
                ->orderBy('assigned_at')
                ->limit(8)
                ->get(),
            'metrics' => [
                'members' => $roster->count(),
                'available' => $roster->where('status', 'Available')->count(),
                'active_batches' => LeadBatch::query()->whereIn('employee_id', $executiveIds)->where('status', 'Active')->count(),
                'overdue' => (clone $pendingFollowUps)->where('followup_date_time', '<', now())->count(),
                'lazy' => (clone $pendingFollowUps)->where('followup_date_time', '<', now()->subHours($lazyThreshold))->distinct()->count('employee_id'),
                'open_leads' => $manager->visibleLeads()->whereIn('status', ['Assigned', 'Contacted', 'Follow-up', 'Interested'])->count(),
                'assigned_month' => $manager->visibleLeads()->where('assigned_at', '>=', $monthStart)->count(),
                'converted_month' => $manager->visibleLeads()->where('assigned_at', '>=', $monthStart)->where('status', 'Converted')->count(),
                'transfers_pending' => $manager->role === 'Team Leader'
                    ? LeadTransfer::query()->where('status', 'Pending')->where(fn ($scope) => $scope->whereIn('from_team_id', $teamIds)->orWhereIn('to_team_id', $teamIds))->count()
                    : null,
            ],
        ];
    }

    /**
     * @param  array{range: string, start: Carbon, end: Carbon, label: string}  $period
     * @return array<string, mixed>
     */
    private function marketingData(array $period): array
    {
        $between = [$period['start'], $period['end']];
        $received = Lead::query()->whereBetween('created_at', $between);
        $total = (clone $received)->count();
        $converted = (clone $received)->where('status', 'Converted')->count();

        return [
            'metrics' => [
                'received' => $total,
                'converted' => $converted,
                'conversion_rate' => $total > 0 ? round($converted / $total * 100, 1) : 0.0,
                'waiting' => Lead::query()->where('status', 'New')->whereNull('current_owner_id')->count(),
                'duplicates' => DuplicateLeadReview::query()->where('status', 'Pending Review')->count(),
                'active_campaigns' => LeadSource::query()->where('is_active', true)->count(),
            ],
            'campaigns' => LeadSource::query()
                ->withCount([
                    'leads as leads_count' => fn ($leads) => $leads->whereBetween('created_at', $between),
                    'leads as converted_count' => fn ($leads) => $leads->whereBetween('created_at', $between)->where('status', 'Converted'),
                    'leads as waiting_count' => fn ($leads) => $leads->where('status', 'New')->whereNull('current_owner_id'),
                ])
                ->orderByDesc('leads_count')
                ->orderBy('campaign_name')
                ->limit(15)
                ->get(),
            'channels' => Lead::query()
                ->whereBetween('leads.created_at', $between)
                ->join('leads_source', 'leads_source.source_id', '=', 'leads.source_id')
                ->selectRaw('leads_source.channel as channel, COUNT(*) as total')
                ->groupBy('leads_source.channel')
                ->orderByDesc('total')
                ->pluck('total', 'channel'),
        ];
    }
}
