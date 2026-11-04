<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $employee = $this->employeeFor($request);

        return view('employees.profile', [
            'employee' => $employee->load(['user', 'details']),
            'pendingRequest' => $employee->changeRequests()->where('status', 'Pending')->latest()->first(),
            'isProfileComplete' => $employee->isProfileComplete(),
            'isAdminEdit' => false,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $employee = $this->employeeFor($request);
        $actor = $this->employeeFor($request);

        return $this->save($request, $employee, $actor);
    }

    public function updateForAdmin(Request $request, Employee $employee): RedirectResponse
    {
        $actor = $this->employeeFor($request);
        abort_unless($actor->isAdmin(), 403);

        return $this->save($request, $employee, $actor);
    }

    private function save(Request $request, Employee $employee, Employee $actor): RedirectResponse
    {
        abort_unless($actor->isAdmin() || $actor->id === $employee->id, 403);
        $user = $employee->user;

        if ($user === null) {
            abort(403, 'This employee has no login account.');
        }

        foreach (['phone_number', 'secondary_contact_number', 'emergency_contact_number'] as $phoneField) {
            if ($request->has($phoneField)) {
                $request->merge([
                    $phoneField => User::normalizePhoneNumber((string) $request->input($phoneField)),
                ]);
            }
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'office_id' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('employees', 'office_id')->ignore($employee->id)],
            'designation' => ['sometimes', 'required', 'string', 'max:100'],
            'joining_date' => ['sometimes', 'required', 'date'],
            'role' => ['sometimes', 'required', Rule::in(Employee::ROLES)],
            'status' => ['sometimes', 'required', Rule::in(['Active', 'Inactive', 'Suspended'])],
            'phone_number' => [
                'sometimes',
                'required',
                'string',
                'max:25',
                new PhoneNumber,
                Rule::unique('users', 'phone_number')->ignore($user->id),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employee_details', 'email')->ignore($employee->details?->id)],
            'secondary_contact_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
            'dob' => ['nullable', 'date', 'before:today'],
            'nid_number' => ['nullable', 'string', 'max:100'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'present_address' => ['nullable', 'string', 'max:5000'],
            'permanent_address' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $profileFields = [
            'email',
            'secondary_contact_number',
            'emergency_contact_name',
            'emergency_contact_number',
            'dob',
            'nid_number',
            'blood_group',
            'present_address',
            'permanent_address',
        ];
        $approvalChanges = Arr::only($validated, EmployeeChangeRequest::APPROVAL_FIELDS);
        $profileChanges = Arr::only($validated, $profileFields);
        $reason = $validated['reason'] ?? null;
        if (blank($profileChanges['nid_number'] ?? null)) {
            unset($profileChanges['nid_number']);
        }
        $hasChangedApprovalValues = false;

        DB::transaction(function () use ($employee, $actor, $user, $approvalChanges, $profileChanges, $reason, &$hasChangedApprovalValues): void {
            $details = $employee->details()->firstOrCreate([]);

            if ($profileChanges !== []) {
                $details->update($profileChanges);
            }
            $employee->setRelation('details', $details);

            $changedApprovalValues = [];

            foreach ($approvalChanges as $field => $value) {
                $currentValue = $field === 'phone_number'
                    ? $user->phone_number
                    : $employee->{$field};
                $currentValue = $currentValue instanceof \DateTimeInterface
                    ? $currentValue->format('Y-m-d')
                    : $currentValue;

                if ((string) $currentValue !== (string) $value) {
                    $changedApprovalValues[$field] = $value;
                }
            }

            if ($changedApprovalValues === []) {
                return;
            }

            $hasChangedApprovalValues = true;

            if ($actor->isAdmin()) {
                $phoneNumber = $changedApprovalValues['phone_number'] ?? null;
                unset($changedApprovalValues['phone_number']);

                $employee->fill($changedApprovalValues);
                $employee->edited_by = $actor->id;
                $employee->save();

                if ($phoneNumber !== null) {
                    $user->update(['phone_number' => $phoneNumber]);
                }

                $employee->changeRequests()->where('status', 'Pending')->update([
                    'status' => 'Rejected',
                    'reviewed_by_employee_id' => $actor->id,
                    'review_note' => 'Superseded by an administrator update.',
                    'reviewed_at' => now(),
                ]);

                return;
            }

            if ($employee->changeRequests()->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'You already have an employee change request awaiting review.',
                ]);
            }

            $employee->changeRequests()->create([
                'requested_by_employee_id' => $actor->id,
                'requested_changes' => $changedApprovalValues,
                'reason' => $reason,
            ]);
        });

        return back()->with('status', $actor->isAdmin()
            ? 'Profile changes saved.'
            : ($hasChangedApprovalValues
                ? 'Profile details saved. Essential changes were sent for approval.'
                : 'Profile details saved.'));
    }

    private function employeeFor(Request $request): Employee
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->employee !== null, 403);

        return $user->employee;
    }
}
