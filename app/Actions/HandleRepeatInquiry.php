<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadFollowup;
use App\Models\LeadInquiry;

/**
 * A customer who is already a lead asks again. Nothing is lost and nobody has to decide by hand:
 *
 * - The inquiry is always logged on the lead, with where it came from.
 * - A lead that was closed (not interested, unreachable, lost, invalid) is reopened and goes back to its
 *   previous owner, outside the daily limit, or to the queue when that person has left.
 * - A lead that is still open gets a follow-up for its owner, but only when this is a new day, so repeated
 *   clicks on the same day do not nag.
 * - A student who is enrolled or paying is only logged.
 */
class HandleRepeatInquiry
{
    public const REOPENABLE = ['Not Interested', 'Unreachable', 'Lost', 'Invalid/Junk'];

    public const OPEN = ['New', 'Assigned', 'Contacted', 'Follow-up', 'Interested'];

    public function __construct(private DistributeLeads $engine, private Notifier $notifier) {}

    /**
     * @return string What was done, in words, for the person who entered the inquiry.
     */
    public function handle(Lead $lead, ?int $sourceId, ?int $courseId, ?Employee $by = null): string
    {
        $owner = $this->activeOwner($lead);
        $lastTouch = $lead->last_action_at ?? $lead->created_at;

        if (in_array($lead->status, self::REOPENABLE, true)) {
            $outcome = $this->reopen($lead, $owner, $sourceId, $courseId);
        } elseif (in_array($lead->status, self::OPEN, true)) {
            $outcome = $this->openLead($lead, $owner, $lastTouch?->isToday() === true);
        } else {
            $outcome = 'Logged. This student is already enrolled or paying.';
            $this->notifier->send($owner, 'lead.repeat', "{$lead->student_name} inquired again", 'They are already enrolled or paying, so no action is needed.', route('leads.workspace', $lead->id), 'blue');
        }

        LeadInquiry::query()->create([
            'lead_id' => $lead->id,
            'source_id' => $sourceId,
            'interested_course_id' => $courseId,
            'outcome' => $outcome,
            'created_by_employee_id' => $by?->id,
        ]);
        LeadEvent::record($lead, $by, 'Inquiry', "Inquired again. {$outcome}");

        return $outcome;
    }

    private function reopen(Lead $lead, ?Employee $owner, ?int $sourceId, ?int $courseId): string
    {
        $was = $lead->status;
        $lead->forceFill(array_filter(['source_id' => $sourceId, 'interested_course_id' => $courseId], fn ($value) => $value !== null))->save();

        if ($owner !== null) {
            $lead->update(['status' => 'New']);
            // Outside the daily limit: this customer already belongs to this person.
            $this->engine->assignBatch($owner, Lead::query()->whereKey($lead->id)->get(), null);
            $this->notifier->send($owner, 'lead.reopened', "{$lead->student_name} is back", "A lead you closed as {$was} asked again. It is back in your tasks.", route('leads.workspace', $lead->id), 'green');

            return "Reopened (was {$was}) and given back to {$owner->name}.";
        }

        $lead->update(['status' => 'New', 'current_owner_id' => null, 'team_id' => null, 'assigned_at' => null]);

        return "Reopened (was {$was}) and returned to the queue, because the previous owner is not available.";
    }

    private function openLead(Lead $lead, ?Employee $owner, bool $sameDay): string
    {
        if ($owner === null) {
            return 'Logged. The lead is still in the queue.';
        }

        if ($sameDay) {
            return 'Logged. It is the same day as the last contact, so no new follow-up.';
        }

        LeadFollowup::query()->create([
            'lead_id' => $lead->id,
            'employee_id' => $owner->id,
            'followup_date_time' => now(),
            'note' => 'The customer inquired again.',
        ]);
        $this->notifier->send($owner, 'lead.repeat', "{$lead->student_name} asked again", 'The customer inquired again today. Call them now.', route('leads.workspace', $lead->id), 'amber');

        return "A follow-up was set for {$owner->name} because the customer asked again.";
    }

    /**
     * The sales person the lead belongs to, or last belonged to, when they can still receive it.
     */
    private function activeOwner(Lead $lead): ?Employee
    {
        $id = $lead->current_owner_id ?? $lead->previous_owner_id;

        return $id === null
            ? null
            : Employee::query()->with('user')->whereKey($id)->where('status', 'Active')->whereIn('role', Employee::SALES_ROLES)->first();
    }
}
