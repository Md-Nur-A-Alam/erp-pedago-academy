<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'lead_id',
    'course_id',
    'course_batch_id',
    'sales_executive_id',
    'secure_uuid',
    'student_name',
    'student_phone',
    'student_email',
    'guardian_name',
    'guardian_phone',
    'base_course_fee',
    'discount_percentage',
    'final_deal_amount',
    'total_installments',
    'status',
])]
class Enrollment extends Model
{
    use HasFactory;

    #[Scope]
    protected function reservingSeat(Builder $query): void
    {
        $query->where('status', '!=', 'Cancelled');
    }

    protected function casts(): array
    {
        return [
            'base_course_fee' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'final_deal_amount' => 'decimal:2',
            'total_installments' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function courseBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'course_batch_id');
    }

    public function salesExecutive(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_executive_id');
    }

    public function installmentSchedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
