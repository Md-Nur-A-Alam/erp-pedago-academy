<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['shift_start_time', 'shift_end_time', 'working_hours', 'is_active'])]
class Shift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'working_hours' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(EmployeeRoster::class);
    }
}
