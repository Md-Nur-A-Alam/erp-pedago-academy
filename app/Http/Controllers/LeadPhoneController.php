<?php

namespace App\Http\Controllers;

use App\Actions\PhoneNumbers;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A lead can have more than one number: a parent's other phone, a sibling's number. Any of them finds the
 * same lead later, so a repeat inquiry from a family member is not mistaken for a new customer.
 */
class LeadPhoneController extends Controller
{
    private const ROLES = ['Admin', 'Sales Executive', 'Team Leader'];

    public function store(Request $request, Lead $lead, PhoneNumbers $phones): RedirectResponse
    {
        $employee = $this->authorizeFor($request, $lead);
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'label' => ['nullable', 'string', 'max:50'],
        ], ['phone_number.required' => 'Enter the phone number to add.']);

        $number = $phones->normalize($validated['phone_number']);

        if ($number === null) {
            throw ValidationException::withMessages(['phone_number' => 'Enter a valid phone number, for example 01XXXXXXXXX.']);
        }

        $owner = $phones->findLead($number);

        if ($owner !== null && $owner->id !== $lead->id) {
            throw ValidationException::withMessages(['phone_number' => "This number already belongs to another lead: {$owner->student_name} (L-{$owner->id}, {$owner->status})."]);
        }

        if ($owner !== null) {
            throw ValidationException::withMessages(['phone_number' => 'This number is already one of this lead\'s numbers.']);
        }

        LeadPhone::query()->create(['lead_id' => $lead->id, 'phone_number' => $number, 'label' => $validated['label'] ?? null, 'added_by_employee_id' => $employee->id]);
        LeadEvent::record($lead, $employee, 'Phone added', "{$employee->name} added the number {$number}".(filled($validated['label'] ?? null) ? " ({$validated['label']})" : '').'.');

        return back()->with('status', "{$number} added. A call from it will now find this lead.");
    }

    public function destroy(Request $request, LeadPhone $leadPhone): RedirectResponse
    {
        $lead = $leadPhone->lead;
        $employee = $this->authorizeFor($request, $lead);

        LeadEvent::record($lead, $employee, 'Phone removed', "{$employee->name} removed the number {$leadPhone->phone_number}.");
        $leadPhone->delete();

        return back()->with('status', 'Number removed.');
    }

    private function authorizeFor(Request $request, Lead $lead): Employee
    {
        $employee = $this->employeeWithRole($request, self::ROLES);
        abort_unless($employee->isAdmin() || $lead->current_owner_id === $employee->id, 403);

        return $employee;
    }
}
