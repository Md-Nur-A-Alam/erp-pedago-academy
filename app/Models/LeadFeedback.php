<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'employee_id',
    'call_status',
    'customer_interest',
    'customer_concern',
    'sales_note',
    'next_action',
    'action_date',
])]
class LeadFeedback extends Model
{
    use HasFactory;

    protected $table = 'lead_feedbacks';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action_date' => 'datetime',
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
