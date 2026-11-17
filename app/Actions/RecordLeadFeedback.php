<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadBatchItem;
use App\Models\LeadFeedback;
use App\Models\LeadFollowup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordLeadFeedback
{
    public const CALL_STATUSES = ['Connected', 'Not Picked', 'Busy', 'Switched Off', 'Wrong Number', 'Not Connected'];

    public const INTERESTS = ['High', 'Medium', 'Low', 'None'];

    /**
     * What each next action does to the lead's status.
     *
     * @var array<string, string>
     */
    public const NEXT_ACTIONS = [
        'Follow-up' => 'Follow-up',
        'Interested' => 'Interested',
        'Not Interested' => 'Not Interested',
        'Unreachable' => 'Unreachable',
        'Lost' => 'Lost',
        'Invalid/Junk' => 'Invalid/Junk',
    ];

    /**
     * Next actions that keep the lead alive and need a call-back time.
     */
    public const NEEDS_FOLLOWUP = ['Follow-up'];

    public const PRIORITIES = ['High', 'Medium', 'Low'];

    /**
     * A call that connected and left the lead open needs a call-back time, or the lead lingers with no plan.
     */
    public const NEEDS_FOLLOWUP_WHEN_CONNECTED = ['Follow-up', 'Interested'];

    /**
     * @param  array{call_status: string, customer_interest: string, customer_concern?: ?string, sales_note: string, next_action: string, followup_date_time?: ?string}  $data
     */
    public function handle(Lead $lead, Employee $executive, array $data): LeadFeedback
    {
        // A wrong number is a dead end: it can only close the lead as Invalid/Junk.
        if ($data['call_status'] === 'Wrong Number') {
            $data['next_action'] = 'Invalid/Junk';
            $data['followup_date_time'] = null;
        }

        return DB::transaction(function () use ($lead, $executive, $data): LeadFeedback {
            $lockedLead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $followUpAt = filled($data['followup_date_time'] ?? null) ? Carbon::parse($data['followup_date_time']) : null;

            $feedback = LeadFeedback::query()->create([
                'lead_id' => $lockedLead->id,
                'employee_id' => $executive->id,
                'call_status' => $data['call_status'],
                'customer_interest' => $data['customer_interest'],
                'customer_concern' => $data['customer_concern'] ?? null,
                'sales_note' => $data['sales_note'],
                'next_action' => $data['next_action'],
                'action_date' => $followUpAt,
            ]);

            $closed = LeadFollowup::query()->where('lead_id', $lockedLead->id)->where('is_done', false)->pluck('id');
            LeadFollowup::query()
                ->where('lead_id', $lockedLead->id)
                ->where('is_done', false)
                ->update(['is_done' => true]);
            $closed->each(fn (int $id) => app(Notifier::class)->resolve("followup:{$id}"));

            if ($followUpAt !== null) {
                LeadFollowup::query()->create([
                    'lead_id' => $lockedLead->id,
                    'employee_id' => $executive->id,
                    'followup_date_time' => $followUpAt,
                    'note' => $data['sales_note'],
                ]);
            }

            $changes = [
                'status' => self::NEXT_ACTIONS[$data['next_action']],
                'last_action_at' => now(),
                'edited_by' => $executive->id,
            ];

            // Details the sales person corrected while talking to the customer. Only what was sent is changed.
            if (filled($data['student_name'] ?? null)) {
                $changes['student_name'] = trim($data['student_name']);
            }
            foreach (['guardian_name', 'remarks'] as $field) {
                if (array_key_exists($field, $data)) {
                    $changes[$field] = filled($data[$field]) ? trim($data[$field]) : null;
                }
            }
            if (filled($data['priority'] ?? null)) {
                $changes['priority'] = $data['priority'];
            }

            $lockedLead->update($changes);

            LeadBatchItem::completeOpenForLead($lockedLead->id, $data['next_action']);

            return $feedback;
        });
    }
}
