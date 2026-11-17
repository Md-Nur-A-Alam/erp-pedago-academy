<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in a lead's history that is not a call: who assigned it, moved it, added a number, and so on.
 */
#[Fillable(['lead_id', 'employee_id', 'type', 'note'])]
class LeadEvent extends Model
{
    public const UPDATED_AT = null;

    public static function record(Lead|int $lead, ?Employee $by, string $type, string $note): self
    {
        return static::query()->create([
            'lead_id' => $lead instanceof Lead ? $lead->id : $lead,
            'employee_id' => $by?->id,
            'type' => $type,
            'note' => mb_substr($note, 0, 500),
        ]);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
