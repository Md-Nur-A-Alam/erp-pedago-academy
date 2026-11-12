<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeRoster;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $currentEmployee = $this->employeeFor($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'supervisor' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
        ]);

        $search = trim($filters['search'] ?? '');
        $selectedSupervisor = isset($filters['supervisor']) ? (int) $filters['supervisor'] : null;
        $statusFilter = $filters['status'] ?? 'active';

        // 1. Summary KPIs
        $totalActiveTeams = Team::query()->where('is_active', true)->count();
        $totalInactiveTeams = Team::query()->where('is_active', false)->count();

        $salesExecutivesInTeam = Employee::query()
            ->where('role', 'Sales Executive')
            ->whereNotNull('team_id')
            ->count();

        $salesExecutivesWithoutTeam = Employee::query()
            ->where('role', 'Sales Executive')
            ->whereNull('team_id')
            ->count();

        $totalSalesExecutives = $salesExecutivesInTeam + $salesExecutivesWithoutTeam;

        // Available now: executives who have active roster on duty today and status 'Available'
        $availableNowCount = EmployeeRoster::query()
            ->whereDate('roster_date', Carbon::today())
            ->where('is_on_duty', true)
            ->where('availability_status', 'Available')
            ->whereHas('employee', function (EloquentBuilder $query): void {
                $query->where('role', 'Sales Executive')->whereNotNull('team_id');
            })
            ->count();

        // Overdue follow-ups count across all teams
        $overdueFollowupsCount = LeadFollowup::query()
            ->where('is_done', false)
            ->where('followup_date_time', '<', Carbon::now())
            ->whereHas('lead', function (EloquentBuilder $query): void {
                $query->whereNotNull('team_id');
            })
            ->count();

        // 2. Teams List Query
        $teamsQuery = Team::query()
            ->with(['teamLeader', 'supervisors', 'members.todayRoster']);

        if ($search !== '') {
            $teamsQuery->where(function (EloquentBuilder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('teamLeader', fn (EloquentBuilder $q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('supervisors', fn (EloquentBuilder $q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('members', fn (EloquentBuilder $q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($selectedSupervisor !== null) {
            $teamsQuery->whereHas('supervisors', function (EloquentBuilder $query) use ($selectedSupervisor): void {
                $query->where('employees.id', $selectedSupervisor);
            });
        }

        if ($statusFilter === 'active') {
            $teamsQuery->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $teamsQuery->where('is_active', false);
        }

        $teams = $teamsQuery->orderBy('name')->paginate(25)->withQueryString();

        // Calculate performance / lead stats per team
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $teamStats = [];
        foreach ($teams as $team) {
            $members = $team->members;
            $membersCount = $members->count();

            // Count available members
            $availableMembersCount = $members->filter(function (Employee $member): bool {
                return $member->todayRoster !== null
                    && $member->todayRoster->is_on_duty
                    && $member->todayRoster->availability_status === 'Available';
            })->count();

            // Open leads: leads assigned to this team that are not terminal
            $openLeadsCount = Lead::query()
                ->where('team_id', $team->id)
                ->whereNotIn('status', ['Converted', 'Lost', 'Not Interested'])
                ->count();

            // Overdue followups for this team
            $overdueCount = LeadFollowup::query()
                ->where('is_done', false)
                ->where('followup_date_time', '<', Carbon::now())
                ->whereHas('lead', fn (EloquentBuilder $q) => $q->where('team_id', $team->id))
                ->count();

            // 30d conversion rate
            $totalLeads30d = Lead::query()
                ->where('team_id', $team->id)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count();

            $convertedLeads30d = Lead::query()
                ->where('team_id', $team->id)
                ->where('status', 'Converted')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count();

            $conversionRate = $totalLeads30d > 0
                ? round(($convertedLeads30d / $totalLeads30d) * 100, 1)
                : 0.0;

            $teamStats[$team->id] = [
                'members_count' => $membersCount,
                'available_count' => $availableMembersCount,
                'open_leads_count' => $openLeadsCount,
                'overdue_count' => $overdueCount,
                'conversion_rate_30d' => $conversionRate,
                'converted_30d' => $convertedLeads30d,
                'total_30d' => $totalLeads30d,
            ];
        }

        // 3. Conversion by team comparison (Last 30 days) for active teams
        $activeTeams = Team::query()->where('is_active', true)->with('members')->get();
        $conversionChart = [];
        $maxRate = 0.0;

        foreach ($activeTeams as $actTeam) {
            $tot = Lead::query()->where('team_id', $actTeam->id)->where('created_at', '>=', $thirtyDaysAgo)->count();
            $conv = Lead::query()->where('team_id', $actTeam->id)->where('status', 'Converted')->where('created_at', '>=', $thirtyDaysAgo)->count();
            $rate = $tot > 0 ? round(($conv / $tot) * 100, 1) : 0.0;

            if ($rate > $maxRate) {
                $maxRate = $rate;
            }

            $conversionChart[] = [
                'id' => $actTeam->id,
                'name' => $actTeam->name,
                'rate' => $rate,
            ];
        }

        // Sort comparison by conversion rate desc
        usort($conversionChart, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        // Normalize bar widths (percent relative to max, or default to 100%)
        foreach ($conversionChart as &$item) {
            $item['width_percent'] = $maxRate > 0 ? round(($item['rate'] / $maxRate) * 100) : 0;
        }
        unset($item);

        // 4. Data for Create / Edit dialogs
        $supervisorsList = Employee::query()
            ->where('role', 'Supervisor')
            ->where('status', 'Active')
            ->with('supervisedTeams')
            ->orderBy('name')
            ->get();

        $potentialLeaders = Employee::query()
            ->whereIn('role', ['Team Leader', 'Supervisor', 'Admin'])
            ->where('status', 'Active')
            ->with('teamsLed')
            ->orderBy('name')
            ->get();

        $allSalesExecutives = Employee::query()
            ->where('role', 'Sales Executive')
            ->where('status', 'Active')
            ->with(['team', 'todayRoster'])
            ->orderBy('name')
            ->get();

        return view('teams.index', [
            'teams' => $teams,
            'teamStats' => $teamStats,
            'stats' => [
                'active_teams' => $totalActiveTeams,
                'inactive_teams' => $totalInactiveTeams,
                'executives_in_team' => $salesExecutivesInTeam,
                'executives_without_team' => $salesExecutivesWithoutTeam,
                'total_executives' => $totalSalesExecutives,
                'available_now' => $availableNowCount,
                'overdue_followups' => $overdueFollowupsCount,
            ],
            'conversionChart' => $conversionChart,
            'filters' => [
                'search' => $search,
                'supervisor' => $selectedSupervisor,
                'status' => $statusFilter,
            ],
            'supervisors' => $supervisorsList,
            'potentialLeaders' => $potentialLeaders,
            'salesExecutives' => $allSalesExecutives,
        ]);
    }

    public function show(Request $request, Team $team): View
    {
        $this->employeeFor($request);

        $team->load(['teamLeader', 'supervisors', 'members.defaultShift', 'members.todayRoster.shift']);

        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $members = $team->members;
        $membersCount = $members->count();

        $availableNowCount = $members->filter(function (Employee $member): bool {
            return $member->todayRoster !== null
                && $member->todayRoster->is_on_duty
                && $member->todayRoster->availability_status === 'Available';
        })->count();

        $openLeadsCount = Lead::query()
            ->where('team_id', $team->id)
            ->whereNotIn('status', ['Converted', 'Lost', 'Not Interested'])
            ->count();

        // Active batches for this team's members
        $inActiveBatchesCount = Lead::query()
            ->where('team_id', $team->id)
            ->whereHas('batchItems', function (EloquentBuilder $query): void {
                $query->where('is_completed', false)
                    ->whereHas('batch', fn (EloquentBuilder $b) => $b->where('status', 'Active'));
            })
            ->count();

        $totalLeads30d = Lead::query()
            ->where('team_id', $team->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $convertedLeads30d = Lead::query()
            ->where('team_id', $team->id)
            ->where('status', 'Converted')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $conversionRate = $totalLeads30d > 0
            ? round(($convertedLeads30d / $totalLeads30d) * 100, 1)
            : 0.0;

        $overdueFollowupsCount = LeadFollowup::query()
            ->where('is_done', false)
            ->where('followup_date_time', '<', Carbon::now())
            ->whereHas('lead', fn (EloquentBuilder $q) => $q->where('team_id', $team->id))
            ->count();

        $lazyExecutivesCount = $members->filter(function (Employee $member): bool {
            return $member->todayRoster !== null
                && $member->todayRoster->availability_status === 'Lazy';
        })->count();

        $pendingTransfersIn = LeadTransfer::query()
            ->where('to_team_id', $team->id)
            ->where('status', 'Pending')
            ->count();

        $pendingTransfersOut = LeadTransfer::query()
            ->where('from_team_id', $team->id)
            ->where('status', 'Pending')
            ->count();

        // Details for each member
        $memberStats = [];
        $executivePerformance = [];
        $maxConv = 0;

        foreach ($members as $member) {
            // Shift display
            $shiftDisplay = 'Day off';
            if ($member->todayRoster && $member->todayRoster->shift) {
                $shiftDisplay = substr($member->todayRoster->shift->shift_start_time, 0, 5).'–'.substr($member->todayRoster->shift->shift_end_time, 0, 5);
            } elseif ($member->defaultShift) {
                $shiftDisplay = substr($member->defaultShift->shift_start_time, 0, 5).'–'.substr($member->defaultShift->shift_end_time, 0, 5);
            }

            // Status display
            $statusDisplay = $member->todayRoster?->availability_status ?? 'Off duty';

            // Active batch progress
            $activeBatch = LeadBatch::query()
                ->where('employee_id', $member->id)
                ->where('status', 'Active')
                ->withCount(['items as completed_count' => fn ($q) => $q->where('is_completed', true)])
                ->first();

            $batchDisplay = $activeBatch ? "{$activeBatch->completed_count}/{$activeBatch->batch_size}" : '—';

            // Member overdue followups
            $memberOverdue = LeadFollowup::query()
                ->where('employee_id', $member->id)
                ->where('is_done', false)
                ->where('followup_date_time', '<', Carbon::now())
                ->count();

            // Member 30d conversion
            $mTotal30d = Lead::query()
                ->where('current_owner_id', $member->id)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count();

            $mConverted30d = Lead::query()
                ->where('current_owner_id', $member->id)
                ->where('status', 'Converted')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count();

            $mRate = $mTotal30d > 0 ? round(($mConverted30d / $mTotal30d) * 100, 1) : 0.0;

            if ($mConverted30d > $maxConv) {
                $maxConv = $mConverted30d;
            }

            $memberStats[$member->id] = [
                'shift' => $shiftDisplay,
                'status' => $statusDisplay,
                'batch' => $batchDisplay,
                'overdue' => $memberOverdue,
                'conversion_rate_30d' => $mRate,
                'converted_count_30d' => $mConverted30d,
            ];

            $executivePerformance[] = [
                'id' => $member->id,
                'name' => $member->name,
                'conversions' => $mConverted30d,
            ];
        }

        // Sort performance by conversions desc
        usort($executivePerformance, fn ($a, $b) => $b['conversions'] <=> $a['conversions']);

        foreach ($executivePerformance as &$ep) {
            $ep['width_percent'] = $maxConv > 0 ? round(($ep['conversions'] / $maxConv) * 100) : 0;
        }
        unset($ep);

        // Team Pipeline by stage
        $pipelineStages = [
            'Follow-up' => Lead::query()->where('team_id', $team->id)->where('status', 'Follow-up')->count(),
            'Interested' => Lead::query()->where('team_id', $team->id)->where('status', 'Interested')->count(),
            'Pending payment' => Lead::query()->where('team_id', $team->id)->whereIn('status', ['Enrolled - Pending Payment', 'Payment Under Review'])->count(),
            'Converted' => Lead::query()->where('team_id', $team->id)->where('status', 'Converted')->count(),
        ];

        $pipelineMax = max(max(array_values($pipelineStages)), 1);
        $pipeline = [];
        foreach ($pipelineStages as $stage => $cnt) {
            $pipeline[] = [
                'stage' => $stage,
                'count' => $cnt,
                'width_percent' => round(($cnt / $pipelineMax) * 100),
            ];
        }

        // Data for edit modal
        $supervisorsList = Employee::query()
            ->where('role', 'Supervisor')
            ->where('status', 'Active')
            ->with('supervisedTeams')
            ->orderBy('name')
            ->get();

        $potentialLeaders = Employee::query()
            ->whereIn('role', ['Team Leader', 'Supervisor', 'Admin'])
            ->where('status', 'Active')
            ->with('teamsLed')
            ->orderBy('name')
            ->get();

        $allSalesExecutives = Employee::query()
            ->where('role', 'Sales Executive')
            ->where('status', 'Active')
            ->with(['team', 'todayRoster'])
            ->orderBy('name')
            ->get();

        return view('teams.show', [
            'team' => $team,
            'stats' => [
                'members' => $membersCount,
                'available_now' => $availableNowCount,
                'open_leads' => $openLeadsCount,
                'in_active_batches' => $inActiveBatchesCount,
                'conversion_rate_30d' => $conversionRate,
                'converted_count_30d' => $convertedLeads30d,
                'total_leads_30d' => $totalLeads30d,
                'overdue_followups' => $overdueFollowupsCount,
                'lazy_executives' => $lazyExecutivesCount,
                'pending_transfers_in' => $pendingTransfersIn,
                'pending_transfers_out' => $pendingTransfersOut,
            ],
            'memberStats' => $memberStats,
            'executivePerformance' => $executivePerformance,
            'pipeline' => $pipeline,
            'supervisors' => $supervisorsList,
            'potentialLeaders' => $potentialLeaders,
            'salesExecutives' => $allSalesExecutives,
        ]);
    }

    public function create(Request $request): View
    {
        $this->employeeFor($request);

        $supervisorsList = Employee::query()
            ->where('role', 'Supervisor')
            ->where('status', 'Active')
            ->with('supervisedTeams')
            ->orderBy('name')
            ->get();

        $potentialLeaders = Employee::query()
            ->whereIn('role', ['Team Leader', 'Supervisor', 'Admin'])
            ->where('status', 'Active')
            ->with('teamsLed')
            ->orderBy('name')
            ->get();

        $allSalesExecutives = Employee::query()
            ->where('role', 'Sales Executive')
            ->where('status', 'Active')
            ->with(['team', 'todayRoster'])
            ->orderBy('name')
            ->get();

        return view('teams.create', [
            'supervisors' => $supervisorsList,
            'potentialLeaders' => $potentialLeaders,
            'salesExecutives' => $allSalesExecutives,
        ]);
    }

    public function edit(Request $request, Team $team): View
    {
        $this->employeeFor($request);

        $team->load(['teamLeader', 'supervisors', 'members']);

        $openLeadsCount = Lead::query()
            ->where('team_id', $team->id)
            ->whereNotIn('status', ['Converted', 'Lost', 'Not Interested'])
            ->count();

        $supervisorsList = Employee::query()
            ->where('role', 'Supervisor')
            ->where('status', 'Active')
            ->with('supervisedTeams')
            ->orderBy('name')
            ->get();

        $potentialLeaders = Employee::query()
            ->whereIn('role', ['Team Leader', 'Supervisor', 'Admin'])
            ->where('status', 'Active')
            ->with('teamsLed')
            ->orderBy('name')
            ->get();

        $allSalesExecutives = Employee::query()
            ->where('role', 'Sales Executive')
            ->where('status', 'Active')
            ->with(['team', 'todayRoster'])
            ->orderBy('name')
            ->get();

        return view('teams.edit', [
            'team' => $team,
            'openLeadsCount' => $openLeadsCount,
            'supervisors' => $supervisorsList,
            'potentialLeaders' => $potentialLeaders,
            'salesExecutives' => $allSalesExecutives,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentEmployee = $this->employeeFor($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:teams,name'],
            'team_leader_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id'),
                Rule::unique('teams', 'team_leader_id'),
            ],
            'supervisors' => ['nullable', 'array'],
            'supervisors.*' => ['integer', Rule::exists('employees', 'id')],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', Rule::exists('employees', 'id')],
        ], [
            'team_leader_id.unique' => 'This team leader is already assigned to lead another team.',
        ]);

        DB::transaction(function () use ($validated, $currentEmployee): void {
            $team = Team::create([
                'name' => $validated['name'],
                'team_leader_id' => $validated['team_leader_id'],
                'is_active' => true,
                'edited_by' => $currentEmployee->id,
            ]);

            // Sync supervisors
            if (! empty($validated['supervisors'])) {
                $team->supervisors()->sync($validated['supervisors']);
            }

            // Assign members
            if (! empty($validated['members'])) {
                Employee::query()
                    ->whereIn('id', $validated['members'])
                    ->update(['team_id' => $team->id]);
            }
        });

        return redirect()->route('teams.index')
            ->with('status', 'Team created successfully.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $currentEmployee = $this->employeeFor($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('teams', 'name')->ignore($team->id)],
            'team_leader_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id'),
                Rule::unique('teams', 'team_leader_id')->ignore($team->id),
            ],
            'supervisors' => ['nullable', 'array'],
            'supervisors.*' => ['integer', Rule::exists('employees', 'id')],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', Rule::exists('employees', 'id')],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'team_leader_id.unique' => 'This team leader is already assigned to lead another team.',
        ]);

        DB::transaction(function () use ($validated, $team, $currentEmployee): void {
            $team->update([
                'name' => $validated['name'],
                'team_leader_id' => $validated['team_leader_id'],
                'is_active' => $validated['is_active'] ?? $team->is_active,
                'edited_by' => $currentEmployee->id,
            ]);

            // Sync supervisors
            $team->supervisors()->sync($validated['supervisors'] ?? []);

            $memberIds = $validated['members'] ?? [];

            // Remove unselected members from this team
            Employee::query()
                ->where('team_id', $team->id)
                ->whereNotIn('id', $memberIds)
                ->update(['team_id' => null]);

            // Assign selected members to this team
            if (! empty($memberIds)) {
                Employee::query()
                    ->whereIn('id', $memberIds)
                    ->update(['team_id' => $team->id]);
            }
        });

        return redirect()->back()
            ->with('status', 'Team updated successfully.');
    }

    public function toggleStatus(Request $request, Team $team): RedirectResponse
    {
        $currentEmployee = $this->employeeFor($request);

        $team->update([
            'is_active' => ! $team->is_active,
            'edited_by' => $currentEmployee->id,
        ]);

        $statusText = $team->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('status', "Team {$team->name} {$statusText} successfully.");
    }

    public function removeMember(Request $request, Team $team, Employee $employee): RedirectResponse
    {
        $this->employeeFor($request);

        if ($employee->team_id === $team->id) {
            $employee->update(['team_id' => null]);
        }

        return redirect()->back()
            ->with('status', "{$employee->name} removed from {$team->name}.");
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->employeeFor($request);

        $leadsCount = Lead::query()->where('team_id', $team->id)->count();
        if ($leadsCount > 0) {
            return redirect()->back()->withErrors([
                'team' => "Team '{$team->name}' cannot be deleted because it has {$leadsCount} lead(s) attached. Deactivate it instead, or reassign leads before deleting.",
            ]);
        }

        DB::transaction(function () use ($team): void {
            Employee::query()->where('team_id', $team->id)->update(['team_id' => null]);
            $team->supervisors()->detach();
            $team->delete();
        });

        return redirect()->route('teams.index')
            ->with('status', "Team '{$team->name}' deleted successfully.");
    }

    private function employeeFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless(
            $employee instanceof Employee
                && in_array($employee->role, ['Admin', 'Supervisor', 'Team Leader'], true),
            403,
        );

        return $employee;
    }
}
