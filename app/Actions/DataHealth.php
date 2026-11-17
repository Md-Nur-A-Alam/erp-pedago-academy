<?php

namespace App\Actions;

use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use App\Models\LeadEvent;
use App\Models\LeadFollowup;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Looks for leads that have quietly got stuck and fixes the ones that are safe to fix. These are the problems the
 * interim script had to diagnose by hand: leads left with someone who has gone, numbers in odd formats,
 * engaged leads with no plan, and batches holding more than they should.
 */
class DataHealth
{
    public function __construct(private PhoneNumbers $phones) {}

    /**
     * @return list<array{key: string, title: string, count: int, detail: string, fix: ?string, fix_label: ?string}>
     */
    public function checks(): array
    {
        $cold = SystemSetting::integer('cold_lead_hours');
        $batchSize = max(1, SystemSetting::integer('lead_batch_size'));

        return [
            [
                'key' => 'orphans', 'title' => 'Leads held by someone who can no longer work them',
                'count' => $this->orphans()->count(),
                'detail' => 'The owner is inactive, suspended or resigned, so nobody is calling these leads. Returning them to the queue lets the engine give them to someone.',
                'fix' => 'orphans', 'fix_label' => 'Return to the queue',
            ],
            [
                'key' => 'no-plan', 'title' => 'Engaged leads with no follow-up',
                'count' => $this->noPlan()->count(),
                'detail' => 'Called at least once and still open, but nothing is scheduled. They come back every day with no plan. Ask the owner to close each or set a follow-up.',
                'fix' => null, 'fix_label' => null,
            ],
            [
                'key' => 'old-queue', 'title' => "Queue leads waiting more than {$cold} hours",
                'count' => Lead::query()->where('status', 'New')->whereNull('current_owner_id')->where('created_at', '<', now()->subHours($cold))->count(),
                'detail' => 'These are Cold. Check that someone is available, the engine is on, and the scheduler is running.',
                'fix' => null, 'fix_label' => null,
            ],
            [
                'key' => 'phones', 'title' => 'Phone numbers in an odd format',
                'count' => $this->oddPhones()->count(),
                'detail' => 'For example +880 or a missing leading zero. Tidying them makes duplicate detection reliable. A number is only changed when no other lead already holds it.',
                'fix' => 'phones', 'fix_label' => 'Tidy the numbers',
            ],
            [
                'key' => 'over-batch', 'title' => "Batches holding more than {$batchSize} open leads",
                'count' => LeadBatch::query()->where('status', 'Active')->withCount(['items as open_items' => fn ($items) => $items->where('is_completed', false)])->get()->filter(fn (LeadBatch $batch): bool => $batch->open_items > $batchSize)->count(),
                'detail' => 'Reactivated and hand-assigned leads can add to a batch beyond its size. This is allowed, but a very large batch slows the person down.',
                'fix' => null, 'fix_label' => null,
            ],
        ];
    }

    /**
     * @return Builder<Lead>
     */
    private function orphans(): Builder
    {
        return Lead::query()
            ->whereIn('status', HandleRepeatInquiry::OPEN)
            ->whereNotNull('current_owner_id')
            ->whereHas('currentOwner', fn ($owner) => $owner->where('status', '!=', 'Active'));
    }

    /**
     * @return Builder<Lead>
     */
    private function noPlan(): Builder
    {
        return Lead::query()
            ->whereIn('status', ['Contacted', 'Follow-up', 'Interested'])
            ->whereNotNull('current_owner_id')
            ->whereDoesntHave('followups', fn ($followUps) => $followUps->where('is_done', false));
    }

    /**
     * @return Collection<int, Lead>
     */
    private function oddPhones(): Collection
    {
        return Lead::query()->get(['id', 'contact_number'])->filter(function (Lead $lead): bool {
            $normalized = $this->phones->normalize($lead->contact_number);

            return $normalized !== null && $normalized !== $lead->contact_number;
        });
    }

    public function returnOrphansToQueue(): int
    {
        return DB::transaction(function (): int {
            $count = 0;

            foreach ($this->orphans()->get() as $lead) {
                LeadBatchItem::completeOpenForLead($lead->id, 'Reassigned');
                LeadFollowup::query()->where('lead_id', $lead->id)->where('is_done', false)->update(['is_done' => true]);
                $lead->forceFill(['previous_owner_id' => $lead->current_owner_id, 'current_owner_id' => null, 'team_id' => null, 'assigned_at' => null, 'status' => 'New'])->save();
                LeadEvent::record($lead, null, 'Returned', 'Returned to the queue because the owner can no longer work it.');
                $count++;
            }

            return $count;
        });
    }

    public function tidyPhones(): int
    {
        $changed = 0;

        foreach ($this->oddPhones() as $lead) {
            $normalized = $this->phones->normalize($lead->contact_number);

            if ($normalized !== null && $this->phones->findLead($normalized, $lead->id) === null) {
                Lead::query()->whereKey($lead->id)->update(['contact_number' => $normalized]);
                $changed++;
            }
        }

        return $changed;
    }
}
