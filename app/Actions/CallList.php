<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\LeadBatchItem;
use App\Models\LeadFollowup;
use Illuminate\Support\Collection;

class CallList
{
    /**
     * A sales person's work for today: the batch, due call-backs and one ranked list to call from.
     *
     * Order: hot new leads, due call-backs (interested customers first, then longest overdue), then warm,
     * cool and cold new leads by freshness, then call-backs that fall later today.
     *
     * @return array{items: Collection<int, LeadBatchItem>, followUps: Collection<int, LeadFollowup>, callNext: Collection<int, array<string, mixed>>}
     */
    public function for(Employee $executive): array
    {
        $items = LeadBatchItem::query()
            ->whereHas('batch', fn ($batch) => $batch->where('employee_id', $executive->id)->where('status', 'Active'))
            ->with(['lead.source:source_id,channel,campaign_name', 'lead.interestedCourse:id,title'])
            ->orderBy('is_completed')
            ->orderBy('id')
            ->get();

        $followUps = LeadFollowup::query()
            ->where('employee_id', $executive->id)
            ->where('is_done', false)
            ->where('followup_date_time', '<=', now()->endOfDay())
            ->with('lead:id,student_name,contact_number,status,priority,created_at')
            ->orderBy('followup_date_time')
            ->get();

        $callNext = collect();

        foreach ($followUps as $followUp) {
            $due = $followUp->followup_date_time->isPast();
            $callNext->push([
                'priority' => $followUp->lead?->priority,
                'lead_id' => $followUp->lead_id,
                'lead' => $followUp->lead,
                'rank' => $due ? ($followUp->lead?->status === 'Interested' ? 1 : 2) : 20,
                'order' => $followUp->followup_date_time->getTimestamp(),
                'label' => $due ? 'Promised call-back' : 'Later today',
                'tone' => $due ? 'red' : 'slate',
                'detail' => $due
                    ? 'Overdue by '.$followUp->followup_date_time->diffForHumans(null, true).($followUp->lead?->priority ? ' · '.$followUp->lead->priority.' priority' : '')
                    : 'Due at '.$followUp->followup_date_time->format('g:i A').($followUp->lead?->priority ? ' · '.$followUp->lead->priority.' priority' : ''),
            ]);
        }

        foreach ($items->where('is_completed', false) as $item) {
            if ($item->lead === null || $callNext->contains('lead_id', $item->lead_id)) {
                continue;
            }

            $temperature = $item->lead->temperature();
            $callNext->push([
                'priority' => $item->lead->priority,
                'lead_id' => $item->lead_id,
                'lead' => $item->lead,
                'rank' => $temperature === 'Hot' ? 0 : 3 + $item->lead->priorityRank(),
                'order' => -$item->lead->created_at->getTimestamp(),
                'label' => $temperature.' lead',
                'tone' => ['Hot' => 'red', 'Warm' => 'amber', 'Cool' => 'blue', 'Cold' => 'slate'][$temperature],
                'detail' => 'Received '.$item->lead->created_at->diffForHumans().($item->lead->priority ? ' · '.$item->lead->priority.' priority' : ''),
            ]);
        }

        return [
            'items' => $items,
            'followUps' => $followUps,
            'callNext' => $callNext->sortBy(fn (array $entry): array => [$entry['rank'], ['High' => 0, 'Medium' => 1, 'Low' => 2][$entry['priority'] ?? ''] ?? 3, $entry['order']])->values(),
        ];
    }
}
