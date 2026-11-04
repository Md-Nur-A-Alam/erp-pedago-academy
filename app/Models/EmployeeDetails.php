<?php

namespace App\Models;

use Database\Factories\EmployeeDetailsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'email',
    'secondary_contact_number',
    'emergency_contact_name',
    'emergency_contact_number',
    'dob',
    'nid_number',
    'blood_group',
    'present_address',
    'permanent_address',
])]
#[Hidden(['nid_number'])]
class EmployeeDetails extends Model
{
    /** @use HasFactory<EmployeeDetailsFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const REQUIRED_PROFILE_FIELDS = [
        'dob',
        'emergency_contact_name',
        'emergency_contact_number',
        'blood_group',
        'present_address',
        'permanent_address',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'nid_number' => 'encrypted',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isComplete(): bool
    {
        foreach (self::REQUIRED_PROFILE_FIELDS as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }
}
