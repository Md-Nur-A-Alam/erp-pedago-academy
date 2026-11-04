<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'source_id',
    'student_name',
    'guardian_name',
    'contact_number',
    'whatsapp_number',
    'alternative_contact_number',
    'email',
    'location',
    'age',
    'class',
    'student_parent_type',
    'student_background',
    'ad_set_id',
    'interested_course_id',
    'status',
    'current_owner_id',
    'previous_owner_id',
    'assigned_by_employee_id',
    'assigned_at',
    'last_action_at',
    'team_id',
    'edited_by',
])]
class Lead extends Model
{
    use HasFactory;

    public const UPDATED_AT = 'edited_at';

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'assigned_at' => 'datetime',
            'last_action_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id', 'source_id');
    }

    public function interestedCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'interested_course_id');
    }

    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_owner_id');
    }

    public function previousOwner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'previous_owner_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by_employee_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'edited_by');
    }

    public function batchItems(): HasMany
    {
        return $this->hasMany(LeadBatchItem::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(LeadFeedback::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(LeadFollowup::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(LeadTransfer::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    public function duplicateReviews(): HasMany
    {
        return $this->hasMany(DuplicateLeadReview::class, 'matched_lead_id');
    }
}
