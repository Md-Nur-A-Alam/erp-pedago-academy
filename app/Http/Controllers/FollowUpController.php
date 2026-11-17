<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadBatchItem;
use App\Models\LeadEvent;
use App\Models\LeadFollowup;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    private const ROLES = ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive'];

    private const TABS = ['overdue' => 'Overdue', 'soon' => 'Coming up', 'later' => 'Scheduled later', 'done' => 'Completed'];

    public function index(Request $request): View
    {
        $viewer = $this->employeeWithRole($request, self::ROLES);
        $reach = $viewer->managedExecutiveIds();
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'employee_id' => ['nullable', 'integer'],
        ]);
        $tab = $filters['tab'] ?? 'overdue';
        $personal = $viewer->role === 'Sales Executive' || $request->routeIs('follow-ups.mine');
        $employeeId = $personal ? $viewer->id : ($filters['employee_id'] ?? null);

        if ($employeeId !== null && $reach !== null && ! in_array((int) $employeeId, $reach, true)) {
            $employeeId = null;
        }

        $counts = [];
        foreach (array_keys(self::TABS) as $key) {
            $counts[$key] = $this->scoped($key, $employeeId, $reach)->count();
        }

        $followUps = $this->scoped($tab, $employeeId, $reach)
            ->with([
                'lead:id,student_name,contact_number,status,interested_course_id,source_id',
                'lead.interestedCourse:id,title',
                'employee:id,name,office_id,team_id',
                'employee.team:id,name',
            ])
            ->orderBy('followup_date_time', $tab === 'done' ? 'desc' : 'asc')
            ->paginate(25)
            ->withQueryString();

        $lazyThreshold = SystemSetting::integer('lazy_threshold_hours');
        $executives = Employee::query()
            ->whereIn('role', Employee::SALES_ROLES)
            ->where('status', 'Active')
            ->when($reach !== null, fn (Builder $query) => $query->whereIn('id', $reach))
            ->with('team:id,name')
            ->withCount([
                'leadFollowups as overdue_count' => fn (Builder $query) => $query
                    ->where('is_done', false)
                    ->where('followup_date_time', '<', now()),
                'leadFollowups as coming_count' => fn (Builder $query) => $query->comingUp(),
                'leadFollowups as lazy_count' => fn (Builder $query) => $query
                    ->where('is_done', false)
                    ->where('followup_date_time', '<', now()->subHours($lazyThreshold)),
            ])
            ->orderBy('name')
            ->get();

        return view('follow-ups.index', [
            'tabs' => self::TABS,
            'tab' => $tab,
            'counts' => $counts,
            'followUps' => $followUps,
            'employeeId' => $employeeId,
            'executives' => $executives,
            'personal' => $personal,
            'lazyExecutives' => $personal ? collect() : $executives->where('lazy_count', '>', 0)->sortByDesc('overdue_count')->values(),
            'viewer' => $viewer,
            'canReassign' => $viewer->isAdmin(),
            'lazyThreshold' => $lazyThreshold,
            'noticeHours' => LeadFollowup::noticeHours(),
        ]);
    }

    /**
     * Move an open follow-up, and its lead, to another executive or back to the central queue.
     */
    public function reassign(Request $request, LeadFollowup $leadFollowup): RedirectResponse
    {
        $admin = $this->employeeWithRole($request);
        $validated = $request->validate([
            'target' => ['required', Rule::in(['executive', 'queue'])],
            'employee_id' => [
                'exclude_unless:target,executive',
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('status', 'Active')->whereIn('role', Employee::SALES_ROLES),
                Rule::notIn([$leadFollowup->employee_id]),
            ],
        ], [
            'employee_id.required' => 'Choose the executive who should take over this follow-up.',
            'employee_id.not_in' => 'This follow-up already belongs to that executive.',
        ]);

        abort_if($leadFollowup->is_done, 422, 'This follow-up is already completed.');

        $message = DB::transaction(function () use ($validated, $leadFollowup, $admin): string {
            $lead = Lead::query()->whereKey($leadFollowup->lead_id)->lockForUpdate()->firstOrFail();
            $lead->previous_owner_id = $lead->current_owner_id ?? $leadFollowup->employee_id;
            $lead->assigned_by_employee_id = $admin->id;
            $lead->edited_by = $admin->id;
            LeadBatchItem::completeOpenForLead($lead->id, 'Reassigned');

            if ($validated['target'] === 'queue') {
                $lead->fill(['current_owner_id' => null, 'team_id' => null, 'assigned_at' => null, 'status' => 'New'])->save();
                LeadFollowup::query()
                    ->where('lead_id', $lead->id)
                    ->where('is_done', false)
                    ->update(['is_done' => true]);

                LeadEvent::record($lead, $admin, 'Returned', "{$admin->name} returned this lead to the queue.");

                return "Lead L-{$lead->id} was returned to the central queue.";
            }

            $executive = Employee::query()->with(['team', 'teamsLed'])->findOrFail($validated['employee_id']);
            $lead->fill([
                'current_owner_id' => $executive->id,
                'team_id' => $executive->effectiveTeam()?->id,
                'assigned_at' => now(),
            ])->save();
            LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->where('is_done', false)
                ->update(['employee_id' => $executive->id]);

            LeadEvent::record($lead, $admin, 'Reassigned', "{$admin->name} reassigned this lead to {$executive->name}.");

            return "Lead L-{$lead->id} and its follow-up now belong to {$executive->name}.";
        });

        return back()->with('status', $message);
    }

    /**
     * @param  list<int>|null  $reach
     * @return Builder<LeadFollowup>
     */
    private function scoped(string $tab, ?int $employeeId, ?array $reach = null): Builder
    {
        $query = LeadFollowup::query()
            ->when($reach !== null, fn (Builder $builder) => $builder->whereIn('employee_id', $reach))
            ->when($employeeId !== null, fn (Builder $builder) => $builder->where('employee_id', $employeeId));

        return match ($tab) {
            'soon' => $query->comingUp(),
            'later' => $query->scheduledLater(),
            'done' => $query->where('is_done', true),
            default => $query->overdue(),
        };
    }
}
