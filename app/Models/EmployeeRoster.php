<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'shift_id',
    'roster_date',
    'is_on_duty',
    'availability_status',
    'last_active_at',
])]
class EmployeeRoster extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'roster_date' => 'date',
            'is_on_duty' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
