<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadBatchItem;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideLeadTransfer
{
    /**
     * Approve a pending transfer and move the lead, its open follow-ups and its batch slot.
     */
    public function approve(LeadTransfer $transfer, ?Employee $decidedBy, ?string $note = null, bool $automatic = false): LeadTransfer
    {
        return DB::transaction(function () use ($transfer, $decidedBy, $note, $automatic): LeadTransfer {
            $locked = $this->lockPending($transfer);
            $lead = Lead::query()->whereKey($locked->lead_id)->lockForUpdate()->firstOrFail();

            if ($lead->current_owner_id !== $locked->to_employee_id) {
                $lead->previous_owner_id = $lead->current_owner_id;
            }
            $lead->current_owner_id = $locked->to_employee_id;
            $lead->team_id = $locked->to_team_id;
            $lead->assigned_by_employee_id = $decidedBy?->id;
            $lead->assigned_at = now();
            $lead->edited_by = $decidedBy?->id;
            $lead->save();

            LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->where('is_done', false)
                ->update(['employee_id' => $locked->to_employee_id]);

            LeadBatchItem::completeOpenForLead($lead->id, 'Transferred');

            $locked->update([
                'status' => 'Approved',
                'is_admin_override' => ! $automatic && $decidedBy?->isAdmin() === true,
                'is_auto_transferred' => $automatic,
                'decided_by_employee_id' => $decidedBy?->id,
                'decision_note' => $note,
                'transferred_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * Record a team leader's approval. The transfer happens once every team leader involved has approved.
     */
    public function approveAsLeader(LeadTransfer $transfer, Employee $leader): LeadTransfer
    {
        return DB::transaction(function () use ($transfer, $leader): LeadTransfer {
            $locked = $this->lockPending($transfer);
            $locked->loadMissing(['fromTeam', 'toTeam']);
            $isSender = $locked->fromTeam?->team_leader_id === $leader->id;
            $isReceiver = $locked->toTeam?->team_leader_id === $leader->id;

            if (! $isSender && ! $isReceiver) {
                throw ValidationException::withMessages(['transfer' => 'You do not lead either team in this transfer.']);
            }

            if ($isSender) {
                $locked->fill(['sender_tl_employee_id' => $leader->id, 'sender_tl_approved_at' => now()]);
            }
            if ($isReceiver) {
                $locked->fill(['receiver_tl_employee_id' => $leader->id, 'receiver_tl_approved_at' => now()]);
            }
            $locked->save();

            $senderDone = $locked->fromTeam?->team_leader_id === null || $locked->sender_tl_approved_at !== null;
            $receiverDone = $locked->toTeam?->team_leader_id === null || $locked->receiver_tl_approved_at !== null;

            return $senderDone && $receiverDone ? $this->approve($locked, $leader) : $locked;
        });
    }

    public function reject(LeadTransfer $transfer, Employee $decidedBy, string $note): LeadTransfer
    {
        return DB::transaction(function () use ($transfer, $decidedBy, $note): LeadTransfer {
            $locked = $this->lockPending($transfer);
            $locked->update([
                'status' => 'Rejected',
                'is_admin_override' => $decidedBy->isAdmin(),
                'decided_by_employee_id' => $decidedBy->id,
                'decision_note' => $note,
            ]);

            return $locked;
        });
    }

    /**
     * Approve every pending transfer that has waited the configured number of days.
     */
    public function approveOverdue(): int
    {
        $deadline = now()->subDays(SystemSetting::integer('transfer_auto_approve_days'));
        $approved = 0;

        LeadTransfer::query()
            ->where('status', 'Pending')
            ->where('created_at', '<=', $deadline)
            ->orderBy('id')
            ->get()
            ->each(function (LeadTransfer $transfer) use (&$approved): void {
                $this->approve($transfer, null, 'Approved automatically after the waiting period.', automatic: true);
                $approved++;
            });

        return $approved;
    }

    private function lockPending(LeadTransfer $transfer): LeadTransfer
    {
        $locked = LeadTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'Pending') {
            throw ValidationException::withMessages([
                'transfer' => 'This transfer has already been '.strtolower($locked->status).'.',
            ]);
        }

        return $locked;
    }
}
