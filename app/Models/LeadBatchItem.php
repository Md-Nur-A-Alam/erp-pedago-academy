<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_id', 'lead_id', 'is_completed', 'action_taken', 'completed_at'])]
class LeadBatchItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LeadBatch::class, 'batch_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Close the open batch slots that hold a lead, and complete any batch left with no open slot.
     */
    public static function completeOpenForLead(int $leadId, string $actionTaken): void
    {
        $items = static::query()
            ->where('lead_id', $leadId)
            ->where('is_completed', false)
            ->get();

        foreach ($items as $item) {
            $item->update(['is_completed' => true, 'action_taken' => $actionTaken, 'completed_at' => now()]);

            $hasOpenItems = static::query()
                ->where('batch_id', $item->batch_id)
                ->where('is_completed', false)
                ->exists();

            if (! $hasOpenItems) {
                LeadBatch::query()
                    ->whereKey($item->batch_id)
                    ->where('status', 'Active')
                    ->update(['status' => 'Completed', 'completed_at' => now()]);
            }
        }
    }
}
