<?php

namespace App\Http\Controllers;

use App\Actions\ReviewEmployeeChangeRequest as ReviewEmployeeChangeRequestAction;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeChangeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $this->employeeFor($request);
        $lowerAuthorityRoles = $employee->lowerAuthorityRoles();

        return view('employee-change-requests.index', [
            'changeRequests' => EmployeeChangeRequest::query()
                ->with(['employee.user', 'requestedBy'])
                ->where('status', 'Pending')
                ->where('employee_id', '!=', $employee->id)
                ->whereHas('employee', fn ($query) => $query->whereIn('role', $lowerAuthorityRoles))
                ->latest()
                ->get(),
        ]);
    }

    public function update(
        Request $request,
        EmployeeChangeRequest $employeeChangeRequest,
        ReviewEmployeeChangeRequestAction $review,
    ): RedirectResponse {
        $employee = $this->employeeFor($request);
        $validated = $request->validate([
            'decision' => ['required', 'in:Approved,Rejected'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['decision'] === 'Approved') {
            $review->approve($employeeChangeRequest, $employee, $validated['review_note'] ?? null);
        } else {
            $review->reject($employeeChangeRequest, $employee, $validated['review_note'] ?? null);
        }

        return back()->with('status', 'Employee change request '.$validated['decision'].'.');
    }

    private function employeeFor(Request $request): Employee
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->employee !== null, 403);

        return $user->employee;
    }
}
