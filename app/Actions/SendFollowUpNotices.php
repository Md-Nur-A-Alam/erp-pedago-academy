<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\LeadFollowup;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

/**
 * Turns follow-up times into notifications.
 *
 * - Coming up: from the reminder window (30 hours by default) before the time until the exact time, the owner
 *   gets one reminder when the window opens.
 * - Overdue: from the exact time onward the owner is told at once and then again every few hours, until a new
 *   follow-up, a conversion or another outcome closes it.
 * - Lazy: an overdue follow-up older than the Lazy threshold is also reported to the owner's managers.
 */
class SendFollowUpNotices
{
    public function __construct(private Notifier $notifier) {}

    /**
     * @return array{reminders: int, overdue: int, repeats: int, lazy: int, cleared: int}
     */
    public function run(): array
    {
        $summary = ['reminders' => 0, 'overdue' => 0, 'repeats' => 0, 'lazy' => 0, 'cleared' => $this->clearFinished()];

        $summary['reminders'] = $this->sendReminders();
        [$summary['overdue'], $summary['repeats']] = $this->sendOverdue();
        $summary['lazy'] = $this->sendLazy();

        return $summary;
    }

    private function sendReminders(): int
    {
        $sent = 0;

        LeadFollowup::query()->comingUp()
            ->whereNull('reminder_sent_at')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->with(['lead:id,student_name,contact_number', 'employee.user'])
            ->each(function (LeadFollowup $followUp) use (&$sent): void {
                $name = $followUp->lead?->student_name ?? 'a lead';
                $this->notifier->send(
                    $followUp->employee,
                    'followup.coming_up',
                    'Follow-up coming up',
                    "Call {$name} at {$followUp->followup_date_time->format('D, M j · g:i A')} (".$followUp->followup_date_time->diffForHumans().').',
                    route('leads.workspace', $followUp->lead_id),
                    'blue',
                    "followup:{$followUp->id}",
                );
                $followUp->update(['reminder_sent_at' => now()]);
                $sent++;
            });

        return $sent;
    }

    /**
     * @return array{int, int}
     */
    private function sendOverdue(): array
    {
        $first = 0;
        $repeats = 0;
        $repeatAfter = now()->subHours(max(1, SystemSetting::integer('followup_overdue_repeat_hours')));

        LeadFollowup::query()->overdue()
            ->where(fn ($query) => $query->where('overdue_alert_sent', false)->orWhere('overdue_notified_at', '<=', $repeatAfter))
            ->with(['lead:id,student_name,contact_number', 'employee.user'])
            ->each(function (LeadFollowup $followUp) use (&$first, &$repeats): void {
                $name = $followUp->lead?->student_name ?? 'a lead';
                $isRepeat = $followUp->overdue_alert_sent;

                $this->notifier->resolve("followup:{$followUp->id}");
                $this->notifier->send(
                    $followUp->employee,
                    'followup.overdue',
                    $isRepeat ? 'Follow-up still overdue' : 'Follow-up overdue',
                    "Call {$name} now. It was due at {$followUp->followup_date_time->format('D, M j · g:i A')} (".$followUp->followup_date_time->diffForHumans().'). It stays overdue until you log a new follow-up, a conversion or another outcome.',
                    route('leads.workspace', $followUp->lead_id),
                    'red',
                    "followup:{$followUp->id}",
                );
                $followUp->update(['overdue_alert_sent' => true, 'overdue_notified_at' => now()]);

                $isRepeat ? $repeats++ : $first++;
            });

        return [$first, $repeats];
    }

    /**
     * Report salespeople who have been overdue for longer than the Lazy threshold to their managers, once a day.
     */
    private function sendLazy(): int
    {
        $threshold = SystemSetting::integer('lazy_threshold_hours');
        $sent = 0;

        $overdueBy = LeadFollowup::query()->overdue()
            ->where('followup_date_time', '<', now()->subHours($threshold))
            ->select('employee_id', DB::raw('COUNT(*) as total'))
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        foreach (Employee::query()->with('user')->whereIn('id', $overdueBy->keys())->where('status', 'Active')->get() as $person) {
            $key = "lazy:{$person->id}:".today()->toDateString();
            $count = $overdueBy[$person->id];
            $label = $count === 1 ? '1 follow-up' : "{$count} follow-ups";

            $this->notifier->send($person, 'followup.lazy', 'You are flagged Lazy', "{$label} overdue for more than {$threshold} hours. You get no new leads until you handle them.", route('follow-ups.mine'), 'red', $key, once: true);
            $this->notifier->send($this->notifier->managersOf($person), 'followup.lazy_team', "{$person->name} is flagged Lazy", "{$label} overdue for more than {$threshold} hours. They receive no new leads until they act.", route('follow-ups.index', ['employee_id' => $person->id]), 'amber', $key, once: true);
            $sent++;
        }

        return $sent;
    }

    /**
     * Clear notices for follow-ups that were closed, whoever closed them.
     */
    private function clearFinished(): int
    {
        $cleared = 0;

        LeadFollowup::query()
            ->where('is_done', true)
            ->where(fn ($query) => $query->whereNotNull('reminder_sent_at')->orWhere('overdue_alert_sent', true))
            ->where('updated_at', '>=', now()->subDays(2))
            ->pluck('id')
            ->each(function (int $id) use (&$cleared): void {
                $this->notifier->resolve("followup:{$id}");
                $cleared++;
            });

        return $cleared;
    }
}
