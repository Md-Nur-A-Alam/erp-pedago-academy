<?php

namespace App\Http\Controllers;

use App\Actions\LeaveBalance;
use App\Actions\Notifier;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveController extends Controller
{
    private const ROLES = ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'MarketingExecutive', 'Accounts'];

    private const STATUSES = ['Pending', 'Approved', 'Rejected', 'Cancelled'];

    public function index(Request $request): View
    {
        $employee = $this->employeeWithRole($request, self::ROLES);

        return view('leave.index', [
            'employee' => $employee,
            'requests' => $employee->leaveRequests()->with(['reviewedBy:id,name', 'firstReviewedBy:id,name'])->latest('start_date')->limit(30)->get(),
            'types' => LeaveRequest::TYPES,
            'balances' => app(LeaveBalance::class)->for($employee),
            'maxDays' => LeaveRequest::MAX_DAYS,
            'chain' => LeaveRequest::approvalChain($employee),
            'certificateDays' => SystemSetting::integer('leave_sick_certificate_days'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employeeWithRole($request, self::ROLES);
        $validated = $request->validate([
            'type' => ['required', Rule::in(LeaveRequest::TYPES)],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'has_certificate' => ['nullable', 'boolean'],
        ], [
            'start_date.after_or_equal' => 'Leave cannot start in the past.',
            'end_date.after_or_equal' => 'The last day cannot be before the first day.',
            'reason.required' => 'Tell your approver why you need this leave.',
        ]);

        $type = $validated['type'];
        $days = LeaveRequest::countDays($validated['start_date'], $validated['end_date'], $type);
        $limit = $type === 'Maternity' ? LeaveRequest::MAX_MATERNITY_DAYS : LeaveRequest::MAX_DAYS;

        if ($days < 1) {
            throw ValidationException::withMessages(['end_date' => 'Friday is the weekly holiday and is not leave. Choose at least one working day.']);
        }

        if ($days > $limit) {
            throw ValidationException::withMessages(['end_date' => "A single {$type} leave request can cover at most {$limit} days."]);
        }

        $balance = app(LeaveBalance::class)->for($employee)[$type];

        if ($balance['remaining'] !== null && $days > $balance['remaining']) {
            throw ValidationException::withMessages(['type' => $this->shortfall($type, $days, $balance)]);
        }

        $certificateDays = SystemSetting::integer('leave_sick_certificate_days');
        if ($type === 'Sick' && $days > $certificateDays && ! $request->boolean('has_certificate')) {
            throw ValidationException::withMessages(['has_certificate' => "Sick leave of more than {$certificateDays} days needs a registered doctor's certificate. Tick the box to confirm you have it."]);
        }

        $clashes = $employee->leaveRequests()
            ->whereIn('status', ['Pending', 'Approved'])
            ->overlapping($validated['start_date'], $validated['end_date'])
            ->exists();

        if ($clashes) {
            throw ValidationException::withMessages(['start_date' => 'You already have a pending or approved leave on some of these dates.']);
        }

        $required = LeaveRequest::approvalsRequired($employee);
        $autoApproved = $required === 0;

        $leave = $employee->leaveRequests()->create([
            'type' => $type,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'has_certificate' => $request->boolean('has_certificate'),
            'days' => $days,
            'required_approvals' => $required,
            'approvals_done' => 0,
            'status' => $autoApproved ? 'Approved' : 'Pending',
            'review_note' => $autoApproved ? 'Approved automatically: administrators need no approval.' : null,
            'reviewed_at' => $autoApproved ? now() : null,
        ]);

        $noun = $days === 1 ? 'day' : 'days';

        if (! $autoApproved) {
            $this->tellApprovers($leave->load('employee.team'));
        }

        return redirect()->route('leave.index')->with('status', $autoApproved
            ? "Leave for {$days} {$noun} is approved. Administrators need no approval."
            : "Leave request for {$days} {$noun} sent to ".strtolower(implode(', then ', LeaveRequest::approvalChain($employee))).'.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $employee = $this->employeeWithRole($request, self::ROLES);
        abort_unless($leaveRequest->employee_id === $employee->id, 403);

        if (! in_array($leaveRequest->status, ['Pending', 'Approved'], true) || $leaveRequest->end_date->isPast() && ! $leaveRequest->end_date->isToday()) {
            throw ValidationException::withMessages(['leave' => 'This request can no longer be cancelled.']);
        }

        $leaveRequest->update(['status' => 'Cancelled']);

        return back()->with('status', 'Leave request cancelled.');
    }

    public function requests(Request $request): View
    {
        $reviewer = $this->employeeWithRole($request, ['Admin', 'Supervisor', 'Team Leader']);
        $filters = $request->validate(['status' => ['nullable', Rule::in(self::STATUSES)]]);
        $status = $filters['status'] ?? 'Pending';

        $base = LeaveRequest::reviewableBy($reviewer);

        return view('leave.requests', [
            'statuses' => self::STATUSES,
            'status' => $status,
            'counts' => (clone $base)->where('status', '!=', 'Pending')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
                ->put('Pending', LeaveRequest::awaiting($reviewer)->count()),
            'requests' => ($status === 'Pending' ? LeaveRequest::awaiting($reviewer) : (clone $base)->where('status', $status))
                ->with(['employee:id,name,office_id,role,team_id', 'employee.team:id,name', 'reviewedBy:id,name', 'firstReviewedBy:id,name'])
                ->orderBy('start_date', $status === 'Pending' ? 'asc' : 'desc')
                ->paginate(20)
                ->withQueryString(),
            'onLeaveToday' => Employee::query()->whereHas('todayLeave')->whereIn('id', $this->scopeIds($reviewer))->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $reviewer = $this->employeeWithRole($request, ['Admin', 'Supervisor', 'Team Leader']);
        $leaveRequest->loadMissing('employee');
        abort_unless($leaveRequest->canBeReviewedBy($reviewer), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'required_if:decision,reject', 'string', 'max:500'],
        ], ['review_note.required_if' => 'Give the reason for rejecting this leave.']);

        if ($leaveRequest->status !== 'Pending') {
            throw ValidationException::withMessages(['leave' => 'This request has already been '.strtolower($leaveRequest->status).'.']);
        }

        $name = $leaveRequest->employee->name;

        if ($validated['decision'] === 'reject') {
            $leaveRequest->update([
                'status' => 'Rejected',
                'reviewed_by_employee_id' => $reviewer->id,
                'review_note' => $validated['review_note'] ?? null,
                'reviewed_at' => now(),
            ]);

            $this->tellApplicant($leaveRequest, 'Leave rejected', $validated['review_note'] ?? '', 'red');

            return back()->with('status', "Leave of {$name} rejected.");
        }

        // The first of two approvals only passes the request on; an administrator can always settle it at once.
        if (! $reviewer->isAdmin() && $leaveRequest->required_approvals >= 2 && $leaveRequest->approvals_done === 0) {
            $leaveRequest->update([
                'approvals_done' => 1,
                'first_reviewed_by_employee_id' => $reviewer->id,
                'first_review_note' => $validated['review_note'] ?? null,
                'first_reviewed_at' => now(),
            ]);

            $this->tellApplicant($leaveRequest, 'Leave: first approval given', 'Your team leader approved. A supervisor or administrator decides next.', 'blue');
            $this->tellApprovers($leaveRequest->refresh()->load('employee.team'));

            return back()->with('status', "First approval given for {$name}. A supervisor or administrator decides next.");
        }

        $leaveRequest->update([
            'status' => 'Approved',
            'approvals_done' => max($leaveRequest->approvals_done, $leaveRequest->required_approvals),
            'reviewed_by_employee_id' => $reviewer->id,
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        $this->tellApplicant($leaveRequest, 'Leave approved', 'Your leave is approved. You will not receive new leads on these days.', 'green');

        return back()->with('status', "Leave of {$name} approved.");
    }

    /**
     * @return list<int>
     */
    private function scopeIds(Employee $reviewer): array
    {
        return LeaveRequest::reviewableBy($reviewer)->pluck('employee_id')->unique()->values()->all();
    }

    /**
     * Tell the people whose decision is needed next that a request is waiting.
     */
    private function tellApprovers(LeaveRequest $leave): void
    {
        $notifier = app(Notifier::class);
        $applicant = $leave->employee;
        $team = $applicant->effectiveTeam();

        $recipients = match (true) {
            $leave->required_approvals >= 2 && $leave->approvals_done === 0 => $team?->teamLeader === null ? collect() : collect([$team->teamLeader]),
            $applicant->role === 'Supervisor' => $notifier->admins(),
            in_array($applicant->role, ['Accounts', 'MarketingExecutive'], true) => $notifier->withRole('Supervisor')->merge($notifier->admins()),
            default => ($team === null ? collect() : $notifier->supervisorsOf($team))->merge($notifier->admins()),
        };

        $notifier->send($recipients->reject(fn (Employee $person): bool => $person->id === $applicant->id), 'leave.requested', "Leave request from {$applicant->name}", "{$leave->type} leave, {$leave->days} ".Str::plural('day', $leave->days).' from '.$leave->start_date->format('M j').'. Reason: '.$leave->reason, route('leave.requests'), 'amber', "leave:{$leave->id}:{$leave->approvals_done}");
    }

    private function tellApplicant(LeaveRequest $leave, string $title, string $body, string $tone): void
    {
        $notifier = app(Notifier::class);
        $notifier->resolve("leave:{$leave->id}:0");
        $notifier->resolve("leave:{$leave->id}:1");
        $notifier->send($leave->employee, 'leave.decided', $title, $body, route('leave.index'), $tone);
    }

    /**
     * @param  array{entitled: ?int, used: int, pending: int, remaining: ?int, basis: string}  $balance
     */
    private function shortfall(string $type, int $days, array $balance): string
    {
        if ($type === 'Maternity' && $balance['entitled'] === 0) {
            return 'Maternity leave needs at least 6 months of service.';
        }

        if ($type === 'Annual' && $balance['entitled'] === 0) {
            return 'Annual leave can be used after one full year of service.';
        }

        return "You asked for {$days} days but have {$balance['remaining']} days of {$type} leave left. Apply for the rest as unpaid leave.";
    }
}
