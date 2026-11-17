<?php

namespace App\Http\Controllers;

use App\Actions\CallList;
use App\Actions\EnrollStudent;
use App\Actions\PhoneNumbers;
use App\Actions\RecordLeadFeedback;
use App\Actions\SubmitPayment;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadTransfer;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesWorkspaceController extends Controller
{
    private const CLOSED_STATUSES = ['Enrolled - Pending Payment', 'Payment Under Review', 'Converted'];

    public function tasks(Request $request, CallList $callList): View
    {
        $executive = $this->employeeWithRole($request, Employee::SALES_ROLES);

        return view('sales.tasks', [
            'executive' => $executive,
            'awaitingPayment' => $executive->ownedLeads()
                ->whereIn('status', ['Enrolled - Pending Payment', 'Payment Under Review'])
                ->orderByDesc('last_action_at')
                ->get(['id', 'student_name', 'contact_number', 'status']),
            'availability' => $executive->effectiveAvailabilityStatus(),
        ] + $callList->for($executive));
    }

    public function show(Request $request, Lead $lead): View
    {
        $viewer = $this->employeeWithRole($request, ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive']);
        abort_unless($viewer->visibleLeads()->whereKey($lead->id)->exists(), 403);

        $lead->load([
            'source', 'interestedCourse', 'currentOwner:id,name,office_id', 'team:id,name',
            'feedbacks' => fn ($feedbacks) => $feedbacks->with('employee:id,name')->latest('id'),
            'followups' => fn ($followUps) => $followUps->with('employee:id,name')->latest('followup_date_time'),
            'transfers' => fn ($transfers) => $transfers->with(['fromEmployee:id,name', 'toEmployee:id,name', 'toTeam:id,name'])->latest('id'),
            'phones',
            'events' => fn ($events) => $events->with('employee:id,name')->latest('id'),
            'inquiries' => fn ($inquiries) => $inquiries->with('source:source_id,campaign_name,channel')->latest('id'),
            'enrollment.course:id,title',
            'enrollment.courseBatch',
            'enrollment.installmentSchedules',
            'enrollment.payments' => fn ($payments) => $payments->latest('id'),
        ]);

        $canWork = $viewer->isSalesPerson() && $lead->current_owner_id === $viewer->id;

        return view('sales.lead', [
            'lead' => $lead,
            'canWork' => $canWork,
            'isClosed' => in_array($lead->status, self::CLOSED_STATUSES, true),
            'enrollment' => $lead->enrollment,
            'canEnroll' => $canWork && in_array($lead->status, EnrollStudent::ENROLLABLE_STATUSES, true) && $lead->enrollment === null,
            'canPay' => $lead->enrollment !== null && ($viewer->isAdmin() || $lead->enrollment->sales_executive_id === $viewer->id),
            'paymentMethods' => SubmitPayment::METHODS,
            'pendingTransfer' => $lead->transfers->firstWhere('status', 'Pending'),
            'callStatuses' => RecordLeadFeedback::CALL_STATUSES,
            'interests' => RecordLeadFeedback::INTERESTS,
            'nextActions' => array_keys(RecordLeadFeedback::NEXT_ACTIONS),
            'priorities' => RecordLeadFeedback::PRIORITIES,
            'canEditPhones' => $canWork || $viewer->isAdmin(),
            'colleagues' => $canWork
                ? Employee::query()->whereIn('role', Employee::SALES_ROLES)->where('status', 'Active')
                    ->where('id', '!=', $viewer->id)->with(['team:id,name', 'teamsLed:id,name,team_leader_id'])->orderBy('name')->get()
                    ->filter(fn (Employee $colleague): bool => $colleague->effectiveTeam() !== null)->values()
                : collect(),
        ]);
    }

    public function feedback(Request $request, Lead $lead, RecordLeadFeedback $record): RedirectResponse
    {
        $executive = $this->ownerOf($request, $lead);
        abort_if(in_array($lead->status, self::CLOSED_STATUSES, true), 422, 'This lead is already in enrollment, so feedback is closed.');

        // A wrong number is a dead end, so it can only close the lead. Settle that before the call-back rule is checked.
        if ($request->input('call_status') === 'Wrong Number') {
            $request->merge(['next_action' => 'Invalid/Junk', 'followup_date_time' => null]);
        }

        $validated = $request->validate([
            'call_status' => ['required', Rule::in(RecordLeadFeedback::CALL_STATUSES)],
            'customer_interest' => ['required', Rule::in(RecordLeadFeedback::INTERESTS)],
            'customer_concern' => ['nullable', 'string', 'max:1000'],
            'sales_note' => ['required', 'string', 'max:2000'],
            'next_action' => ['required', Rule::in(array_keys(RecordLeadFeedback::NEXT_ACTIONS))],
            'followup_date_time' => [
                'nullable',
                Rule::requiredIf(fn (): bool => in_array($request->input('next_action'), RecordLeadFeedback::NEEDS_FOLLOWUP, true)
                    || ($request->input('call_status') === 'Connected' && in_array($request->input('next_action'), RecordLeadFeedback::NEEDS_FOLLOWUP_WHEN_CONNECTED, true))),
                'date',
                'after:now',
            ],
            'priority' => ['nullable', Rule::in(RecordLeadFeedback::PRIORITIES)],
            'student_name' => ['nullable', 'string', 'max:150'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'followup_date_time.required' => 'The call connected and the lead is still open, so choose when you will call again.',
            'followup_date_time.after' => 'The call-back time must be in the future.',
            'sales_note.required' => 'Write a short note about the call.',
        ]);

        $record->handle($lead, $executive, $validated);

        return redirect()->route('sales.tasks')->with('status', "Feedback saved for {$lead->student_name}. Status is now ".RecordLeadFeedback::NEXT_ACTIONS[$validated['next_action']].'.');
    }

    /**
     * Log that the owner sent the thank-you message, then open WhatsApp with it ready to send. The system cannot
     * see whether the message was actually sent, only that the button was used.
     */
    public function whatsappThanks(Request $request, Lead $lead): RedirectResponse
    {
        $executive = $this->ownerOf($request, $lead);
        $number = $lead->whatsapp_number ?: $lead->contact_number;
        abort_if(blank($number), 422, 'This lead has no phone number.');

        $message = strtr(SystemSetting::valueOf('whatsapp_thanks_message'), [
            '{নাম}' => $lead->student_name, '{name}' => $lead->student_name,
            '{অভিভাবক}' => $lead->guardian_name ?: $lead->student_name, '{guardian}' => $lead->guardian_name ?: $lead->student_name,
            '{সেলস}' => $executive->name, '{sales}' => $executive->name,
        ]);

        LeadEvent::record($lead, $executive, 'WhatsApp', "{$executive->name} opened WhatsApp to thank the customer ({$number}).");

        return redirect()->away('https://wa.me/'.app(PhoneNumbers::class)->forWhatsApp($number).'?text='.rawurlencode($message));
    }

    public function requestTransfer(Request $request, Lead $lead): RedirectResponse
    {
        $executive = $this->ownerOf($request, $lead);
        $validated = $request->validate([
            'to_employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereIn('role', Employee::SALES_ROLES)->where('status', 'Active'), Rule::notIn([$executive->id])],
            'reason' => ['required', 'string', 'max:500'],
        ], ['to_employee_id.required' => 'Choose the executive who should take this lead.', 'reason.required' => 'Explain why this lead should move.']);

        $target = Employee::query()->findOrFail($validated['to_employee_id']);
        $fromTeamId = $lead->team_id ?? $executive->effectiveTeam()?->id;
        $toTeamId = $target->effectiveTeam()?->id;

        if ($fromTeamId === null || $toTeamId === null) {
            throw ValidationException::withMessages(['to_employee_id' => 'Both executives must belong to a team before a lead can be transferred.']);
        }
        if (LeadTransfer::query()->where('lead_id', $lead->id)->where('status', 'Pending')->exists()) {
            throw ValidationException::withMessages(['reason' => 'A transfer for this lead is already waiting for approval.']);
        }

        LeadTransfer::query()->create([
            'lead_id' => $lead->id,
            'from_employee_id' => $executive->id,
            'to_employee_id' => $target->id,
            'from_team_id' => $fromTeamId,
            'to_team_id' => $toTeamId,
            'reason' => $validated['reason'],
            'status' => 'Pending',
        ]);

        return back()->with('status', "Transfer to {$target->name} requested. Team leaders will approve it, or it is approved automatically after the waiting period.");
    }

    private function ownerOf(Request $request, Lead $lead): Employee
    {
        $executive = $this->employeeWithRole($request, Employee::SALES_ROLES);
        abort_unless($lead->current_owner_id === $executive->id, 403);

        return $executive;
    }
}
