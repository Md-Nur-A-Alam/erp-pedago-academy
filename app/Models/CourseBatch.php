<?php

namespace App\Models;

use Database\Factories\CourseBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id',
    'batch_number',
    'batch_name',
    'short_code',
    'capacity',
    'is_latest',
    'start_date',
])]
class CourseBatch extends Model
{
    /** @use HasFactory<CourseBatchFactory> */
    use HasFactory;

    protected $table = 'course_batch';

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_latest' => 'boolean',
            'start_date' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'course_batch_id');
    }

    public function getStudentsAdmittedAttribute(): int
    {
        return (int) ($this->attributes['students_admitted']
            ?? $this->enrollments()->reservingSeat()->count());
    }

    public function getVacancyAttribute(): int
    {
        return max(0, $this->capacity - $this->students_admitted);
    }
}
