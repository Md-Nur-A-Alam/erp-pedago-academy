<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra number that belongs to the same lead, such as a parent's other phone.
 */
#[Fillable(['lead_id', 'phone_number', 'label', 'added_by_employee_id'])]
class LeadPhone extends Model
{
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'added_by_employee_id');
    }
}
