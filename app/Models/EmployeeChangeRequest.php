<?php

namespace App\Models;

use Database\Factories\EmployeeChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'requested_by_employee_id',
    'requested_changes',
    'reason',
    'status',
    'reviewed_by_employee_id',
    'review_note',
    'reviewed_at',
])]
class EmployeeChangeRequest extends Model
{
    /** @use HasFactory<EmployeeChangeRequestFactory> */
    use HasFactory;

    public const APPROVAL_FIELDS = [
        'name',
        'office_id',
        'designation',
        'joining_date',
        'role',
        'status',
        'phone_number',
    ];

    protected function casts(): array
    {
        return [
            'requested_changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by_employee_id');
    }

    public function canBeReviewedBy(Employee $approver): bool
    {
        return $this->status === 'Pending' && $this->employee->canBeReviewedBy($approver);
    }
}
