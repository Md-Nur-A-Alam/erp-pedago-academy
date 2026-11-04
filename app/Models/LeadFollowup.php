<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'employee_id',
    'followup_date_time',
    'note',
    'is_done',
    'overdue_alert_sent',
])]
class LeadFollowup extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'followup_date_time' => 'datetime',
            'is_done' => 'boolean',
            'overdue_alert_sent' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
