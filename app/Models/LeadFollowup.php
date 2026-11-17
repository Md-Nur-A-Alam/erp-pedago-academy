<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
    'reminder_sent_at',
    'overdue_notified_at',
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
            'reminder_sent_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * Not done yet and past its exact time. It stays overdue until a new follow-up, a conversion or another outcome is logged.
     *
     * @param  Builder<LeadFollowup>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('is_done', false)->where('followup_date_time', '<', now());
    }

    /**
     * Not done yet and inside the reminder window, from the notice hours before its time up to the exact time.
     *
     * @param  Builder<LeadFollowup>  $query
     */
    public function scopeComingUp(Builder $query): void
    {
        $query->where('is_done', false)
            ->where('followup_date_time', '>=', now())
            ->where('followup_date_time', '<=', now()->addHours(self::noticeHours()));
    }

    /**
     * Not done yet and further away than the reminder window.
     *
     * @param  Builder<LeadFollowup>  $query
     */
    public function scopeScheduledLater(Builder $query): void
    {
        $query->where('is_done', false)->where('followup_date_time', '>', now()->addHours(self::noticeHours()));
    }

    public static function noticeHours(): int
    {
        return max(1, SystemSetting::integer('followup_notice_hours'));
    }

    /**
     * Overdue, Coming up, Scheduled or Done.
     */
    public function phase(): string
    {
        return match (true) {
            $this->is_done => 'Done',
            $this->followup_date_time->isPast() => 'Overdue',
            $this->followup_date_time->lte(now()->addHours(self::noticeHours())) => 'Coming up',
            default => 'Scheduled',
        };
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
