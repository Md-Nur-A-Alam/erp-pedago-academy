<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'title',
    'code',
    'description',
    'conditions',
    'subject_id',
    'course_topic_id',
    'version',
    'category',
    'base_fee',
    'is_active',
    'edited_by',
])]
class Course extends Model
{
    use HasFactory;

    public const UPDATED_AT = 'edited_at';

    protected function casts(): array
    {
        return [
            'base_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'interested_course_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(LeadSource::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CourseTopic::class, 'course_topic_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(CourseBatch::class);
    }

    public function latestBatch(): HasOne
    {
        return $this->hasOne(CourseBatch::class)->where('is_latest', true);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'edited_by');
    }
}
