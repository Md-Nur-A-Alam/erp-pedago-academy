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
    ];

    /**
     * Next actions that keep the lead alive and need a call-back time.
     */
    public const NEEDS_FOLLOWUP = ['Follow-up'];

    /**
     * @param  array{call_status: string, customer_interest: string, customer_concern?: ?string, sales_note: string, next_action: string, followup_date_time?: ?string}  $data
     */
    public function handle(Lead $lead, Employee $executive, array $data): LeadFeedback
    {
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

            LeadFollowup::query()
                ->where('lead_id', $lockedLead->id)
                ->where('is_done', false)
                ->update(['is_done' => true]);

            if ($followUpAt !== null) {
                LeadFollowup::query()->create([
                    'lead_id' => $lockedLead->id,
                    'employee_id' => $executive->id,
                    'followup_date_time' => $followUpAt,
                    'note' => $data['sales_note'],
                ]);
            }

            $lockedLead->update([
                'status' => self::NEXT_ACTIONS[$data['next_action']],
                'last_action_at' => now(),
                'edited_by' => $executive->id,
            ]);

            LeadBatchItem::completeOpenForLead($lockedLead->id, $data['next_action']);

            return $feedback;
        });
    }
}
