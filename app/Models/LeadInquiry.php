<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every time a customer asked again: the first inquiry and each repeat, with where it came from.
 */
#[Fillable(['lead_id', 'source_id', 'interested_course_id', 'outcome', 'created_by_employee_id'])]
class LeadInquiry extends Model
{
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id', 'source_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'interested_course_id');
    }
}
