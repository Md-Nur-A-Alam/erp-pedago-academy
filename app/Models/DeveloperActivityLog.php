<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['developer_employee_id', 'action', 'target_employee_id', 'details', 'ip_address'])]
class DeveloperActivityLog extends Model
{
    public const UPDATED_AT = null;

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'developer_employee_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }
}
