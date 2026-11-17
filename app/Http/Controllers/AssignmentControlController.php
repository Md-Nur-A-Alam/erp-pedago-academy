<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Per sales person: take them off automatic distribution (Manual) or limit how many leads the engine gives
 * them in a day. Leads handed over by a person (queue assignment, reassignment, a returning customer) are
 * never counted against the limit.
 */
class AssignmentControlController extends Controller
{
    public function index(Request $request): View
    {
        $this->employeeWithRole($request);

        return view('assignment-controls.index', [
            'people' => Employee::query()
                ->whereIn('role', Employee::SALES_ROLES)
                ->where('status', 'Active')
                ->with('team:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->employeeWithRole($request);
        abort_unless(in_array($employee->role, Employee::SALES_ROLES, true), 404);

        $validated = $request->validate([
            'assign_mode' => ['required', Rule::in(['Auto', 'Manual'])],
            'daily_lead_cap' => ['nullable', 'integer', 'min:0', 'max:500'],
        ], ['daily_lead_cap.integer' => 'The daily limit must be a whole number. Leave it empty for no limit.']);

        $employee->update([
            'assign_mode' => $validated['assign_mode'],
            'daily_lead_cap' => $validated['daily_lead_cap'] ?? null,
        ]);

        return back()->with('status', "{$employee->name}: ".($validated['assign_mode'] === 'Manual' ? 'automatic leads switched off' : 'automatic leads on').(($validated['daily_lead_cap'] ?? null) === null ? ', no daily limit.' : ", at most {$validated['daily_lead_cap']} a day."));
    }
}
