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
    'priority',
    'remarks',
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

    /**
     * Channels where the person contacted us first, so intent is high.
     */
    public const HIGH_INTENT_CHANNELS = ['phone', 'reference', 'wp', 'website', 'googleform'];

    /**
     * How fresh the lead is, measured from when it was received: Hot, Warm, Cool or Cold.
     */
    public function temperature(): string
    {
        $ageMinutes = $this->created_at === null ? PHP_INT_MAX : $this->created_at->diffInMinutes(now());

        return match (true) {
            $ageMinutes <= SystemSetting::integer('hot_lead_minutes') => 'Hot',
            $ageMinutes <= SystemSetting::integer('warm_lead_hours') * 60 => 'Warm',
            $ageMinutes <= SystemSetting::integer('cold_lead_hours') * 60 => 'Cool',
            default => 'Cold',
        };
    }

    /**
     * Sort key for calling and distribution: lower is more urgent.
     */
    public function priorityRank(): int
    {
        $rank = ['Hot' => 0, 'Warm' => 2, 'Cool' => 4, 'Cold' => 6][$this->temperature()];
        $highIntent = in_array($this->source?->channel, self::HIGH_INTENT_CHANNELS, true);

        return $rank + ($highIntent ? 0 : 1);
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

    protected static function booted(): void
    {
        static::created(function (Lead $lead): void {
            LeadInquiry::query()->create(['lead_id' => $lead->id, 'source_id' => $lead->source_id, 'interested_course_id' => $lead->interested_course_id, 'outcome' => 'First inquiry']);
        });
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

    public function latestPendingFollowup(): HasOne
    {
        return $this->hasOne(LeadFollowup::class)
            ->where('is_done', false)
            ->latestOfMany('followup_date_time');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(LeadTransfer::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(LeadPhone::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(LeadInquiry::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class);
    }

    public function duplicateReviews(): HasMany
    {
        return $this->hasMany(DuplicateLeadReview::class, 'matched_lead_id');
    }
}
