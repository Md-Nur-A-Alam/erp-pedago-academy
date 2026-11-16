<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeRoster;
use App\Models\Shift;
use App\Models\User;
use App\Rules\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', Rule::in(Employee::ROLES)],
            'status' => ['nullable', Rule::in(['Active', 'Inactive', 'Suspended'])],
            'availability' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in(['name', 'office_id', 'designation', 'role', 'phone', 'team', 'status', 'joining_date', 'availability'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filters['sort'] ?? 'name';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Employee::query()
            ->with(['user', 'team.shift', 'teamsLed', 'supervisedTeams', 'todayRoster.shift', 'defaultShift'])
            ->when(
                filled($filters['search'] ?? null),
                function (Builder $query) use ($filters): void {
                    $search = '%'.trim($filters['search']).'%';
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('employees.name', 'like', $search)
                            ->orWhere('employees.office_id', 'like', $search)
                            ->orWhere('employees.designation', 'like', $search)
                            ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('phone_number', 'like', $search))
                            ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery->where('name', 'like', $search));
                    });
                },
            )
            ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->where('employees.role', $filters['role']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('employees.status', $filters['status']))
            ->when(
                filled($filters['availability'] ?? null),
                function (Builder $query) use ($filters): void {
                    $avail = $filters['availability'];
                    if ($avail === 'Available') {
                        $query->where(function (Builder $q): void {
                            $q->whereHas('todayRoster', fn (Builder $rq) => $rq->where('is_on_duty', true)->where('availability_status', 'Available'))
                                ->orWhere(function (Builder $q2): void {
                                    $q2->whereDoesntHave('todayRoster')
                                        ->where('employees.status', 'Active')
                                        ->where(function (Builder $q3): void {
                                            $q3->whereHas('team', fn (Builder $tq) => $tq->whereNotNull('shift_id'))
                                                ->orWhereNotNull('employees.shift_id');
                                        });
                                });
                        });
                    } elseif ($avail === 'Off Duty') {
                        $query->where(function (Builder $q): void {
                            $q->whereHas('todayRoster', fn (Builder $rq) => $rq->where('is_on_duty', false)->orWhere('availability_status', 'Off Duty'))
                                ->orWhere(function (Builder $q2): void {
                                    $q2->whereDoesntHave('todayRoster')
                                        ->where(function (Builder $q3): void {
                                            $q3->whereDoesntHave('team', fn (Builder $tq) => $tq->whereNotNull('shift_id'))
                                                ->whereNull('employees.shift_id');
                                        });
                                });
                        });
                    } else {
                        $query->whereHas('todayRoster', fn (Builder $rq) => $rq->where('availability_status', $avail));
                    }
                },
            );

        if ($sort === 'team') {
            $query->select('employees.*')
                ->leftJoin('teams', 'employees.team_id', '=', 'teams.id')
                ->orderByRaw('COALESCE(teams.name, (SELECT name FROM teams WHERE teams.team_leader_id = employees.id LIMIT 1), "") '.$direction)
                ->orderBy('employees.name', 'asc');
        } elseif ($sort === 'phone') {
            $query->select('employees.*')
                ->leftJoin('users', 'employees.user_id', '=', 'users.id')
                ->orderBy('users.phone_number', $direction)
                ->orderBy('employees.name', 'asc');
        } elseif ($sort === 'availability') {
            $query->select('employees.*')
                ->leftJoin('employee_rosters', function ($join): void {
                    $join->on('employees.id', '=', 'employee_rosters.employee_id')
                        ->whereDate('employee_rosters.roster_date', today());
                })
                ->leftJoin('teams', 'employees.team_id', '=', 'teams.id')
                ->orderByRaw('CASE 
                    WHEN employee_rosters.id IS NOT NULL THEN employee_rosters.availability_status
                    WHEN employees.status = "Active" AND (teams.shift_id IS NOT NULL OR employees.shift_id IS NOT NULL) THEN "Available"
                    ELSE "Off Duty"
                END '.$direction)
                ->orderBy('employees.name', 'asc');
        } else {
            $query->select('employees.*')
                ->orderBy('employees.'.$sort, $direction);
            if ($sort !== 'name') {
                $query->orderBy('employees.name', 'asc');
            }
        }

        return view('employees.index', [
            'employees' => $query->paginate(15)->withQueryString(),
            'roles' => Employee::ROLES,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'availability' => $filters['availability'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('employees.create', [
            'roles' => Employee::ROLES,
            'nextOfficeId' => Employee::nextOfficeId(),
        ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        $this->authorizeAdmin($request);

        $employee->load(['user', 'details', 'team.shift', 'defaultShift']);

        $shifts = Shift::query()->where('is_active', true)->orderBy('shift_start_time')->get();
        $dateOverrides = $employee->rosters()
            ->with('shift')
            ->orderBy('roster_date', 'desc')
            ->take(30)
            ->get();

        return view('employees.profile', [
            'employee' => $employee,
            'pendingRequest' => $employee->changeRequests()->where('status', 'Pending')->latest()->first(),
            'isProfileComplete' => $employee->isProfileComplete(),
            'isAdminEdit' => true,
            'shifts' => $shifts,
            'dateOverrides' => $dateOverrides,
        ]);
    }

    public function storeRosterOverride(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'roster_date' => ['required', 'date'],
            'is_on_duty' => ['required', 'boolean'],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'shift_start_time' => ['nullable', 'date_format:H:i'],
            'shift_end_time' => ['nullable', 'date_format:H:i'],
            'availability_status' => ['nullable', Rule::in(['Available', 'Busy', 'Unavailable', 'Off Duty', 'Lazy', 'Resigned'])],
        ]);

        $resolvedShiftId = null;
        if (! empty($validated['shift_start_time']) && ! empty($validated['shift_end_time'])) {
            $start = Carbon::createFromFormat('H:i', $validated['shift_start_time']);
            $end = Carbon::createFromFormat('H:i', $validated['shift_end_time']);
            if ($end->lessThan($start)) {
                $end->addDay();
            }
            $workingHours = round($start->diffInMinutes($end) / 60, 2);

            $shift = Shift::firstOrCreate(
                [
                    'shift_start_time' => $start->format('H:i:s'),
                    'shift_end_time' => $end->format('H:i:s'),
                ],
                [
                    'working_hours' => $workingHours,
                    'is_active' => true,
                ]
            );
            $resolvedShiftId = $shift->id;
        } elseif (! empty($validated['shift_id'])) {
            $resolvedShiftId = (int) $validated['shift_id'];
        } elseif ((bool) $validated['is_on_duty']) {
            $resolvedShiftId = $employee->team?->shift_id ?? $employee->shift_id;
        }

        $isOnDuty = (bool) $validated['is_on_duty'];
        $status = $validated['availability_status'] ?? ($isOnDuty ? 'Available' : 'Off Duty');

        EmployeeRoster::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'roster_date' => $validated['roster_date'],
            ],
            [
                'shift_id' => $resolvedShiftId,
                'is_on_duty' => $isOnDuty,
                'availability_status' => $status,
            ]
        );

        return redirect()->back()->with('status', 'Duty time override for '.Carbon::parse($validated['roster_date'])->format('M d, Y').' saved successfully.');
    }

    public function destroyRosterOverride(Request $request, Employee $employee, EmployeeRoster $roster): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($roster->employee_id === $employee->id) {
            $date = $roster->roster_date->format('M d, Y');
            $roster->delete();

            return redirect()->back()->with('status', "Duty time override for {$date} removed. Employee reverted to team primary shift.");
        }

        return redirect()->back()->withErrors(['roster' => 'Invalid roster record.']);
    }

    public function update(
        Request $request,
        Employee $employee,
        EmployeeProfileController $profiles,
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        return $profiles->updateForAdmin($request, $employee);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (! $request->filled('office_id')) {
            $request->merge(['office_id' => Employee::nextOfficeId()]);
        }

        $request->merge([
            'phone_number' => User::normalizePhoneNumber((string) $request->input('phone_number', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'office_id' => ['required', 'string', 'max:50', 'unique:employees,office_id'],
            'designation' => ['required', 'string', 'max:100'],
            'joining_date' => ['required', 'date'],
            'role' => ['required', Rule::in(Employee::ROLES)],
            'phone_number' => ['required', 'string', 'max:25', new PhoneNumber, 'unique:users,phone_number'],
            'password' => ['required', 'string', 'min:6', 'max:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'phone_number' => $validated['phone_number'],
                'password' => $validated['password'],
                'must_change_password' => true,
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'office_id' => $validated['office_id'],
                'designation' => $validated['designation'],
                'joining_date' => $validated['joining_date'],
                'role' => $validated['role'],
                'status' => 'Active',
            ]);

            $employee->details()->create();
        });

        return redirect()->route('employees.index')
            ->with('status', 'Employee account created. The employee must change the temporary password at first login.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user() instanceof User && $request->user()->employee?->isAdmin(), 403);
    }
}
