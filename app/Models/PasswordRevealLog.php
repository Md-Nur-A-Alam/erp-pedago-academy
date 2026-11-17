<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_employee_id', 'target_employee_id', 'action', 'reason', 'ip_address'])]
class PasswordRevealLog extends Model
{
    public const UPDATED_AT = null;

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'admin_employee_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }
}
