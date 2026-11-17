<?php

namespace App\Actions;

use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\LeadFollowup;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PerformanceMetrics
{
    /**
     * Minutes after the promised time that a call-back still counts as kept on time.
     */
    public const FOLLOWUP_GRACE_MINUTES = 60;

    /**
     * Company-wide targets, set by an administrator under Settings.
     *
     * @return array{calls_per_day: int, first_call_minutes: int, followup_on_time: int, conversion: int}
     */
    public function targets(): array
    {
        return [
            'calls_per_day' => SystemSetting::integer('kpi_calls_per_day'),
            'first_call_minutes' => SystemSetting::integer('kpi_first_call_minutes'),
            'followup_on_time' => SystemSetting::integer('kpi_followup_on_time_percent'),
            'conversion' => SystemSetting::integer('kpi_conversion_percent'),
        ];
    }

    /**
     * Days in the period up to today, leaving out Fridays (the weekly day off).
     */
    public function workingDays(Carbon $start, Carbon $end): int
    {
        $last = $end->copy()->min(now())->startOfDay();
        $days = 0;

        for ($day = $start->copy()->startOfDay(); $day->lessThanOrEqualTo($last); $day->addDay()) {
            if (! $day->isFriday()) {
                $days++;
            }
        }

        return max(1, $days);
    }

    /**
     * One row of figures for each sales person.
     *
     * @param  list<int>  $employeeIds
     * @return Collection<int, array<string, mixed>> keyed by employee id
     */
    public function forEmployees(array $employeeIds, Carbon $start, Carbon $end): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        $between = [$start, $end];
        $workingDays = $this->workingDays($start, $end);

        $assigned = Lead::query()->whereIn('current_owner_id', $employeeIds)->whereBetween('assigned_at', $between);
        $assignedCount = (clone $assigned)->selectRaw('current_owner_id, COUNT(*) as total')->groupBy('current_owner_id')->pluck('total', 'current_owner_id');
        $convertedCount = (clone $assigned)->where('status', 'Converted')->selectRaw('current_owner_id, COUNT(*) as total')->groupBy('current_owner_id')->pluck('total', 'current_owner_id');

        $calls = LeadFeedback::query()->whereIn('employee_id', $employeeIds)->whereBetween('created_at', $between)
            ->selectRaw('employee_id, COUNT(*) as total')->groupBy('employee_id')->pluck('total', 'employee_id');

        $firstCalls = DB::table('lead_feedbacks')->selectRaw('lead_id, MIN(created_at) as first_call')->groupBy('lead_id');
        $responseRows = Lead::query()
            ->whereIn('current_owner_id', $employeeIds)
            ->whereBetween('assigned_at', $between)
            ->leftJoinSub($firstCalls, 'first_calls', 'first_calls.lead_id', '=', 'leads.id')
            ->get(['leads.current_owner_id', 'leads.assigned_at', 'first_calls.first_call']);

        $followUps = LeadFollowup::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('followup_date_time', [$start, $end->copy()->min(now())])
            ->get(['employee_id', 'followup_date_time', 'is_done', 'updated_at']);

        $revenue = Payment::query()
            ->join('enrollments', 'enrollments.id', '=', 'payments.enrollment_id')
            ->where('payments.status', 'Verified')
            ->whereBetween('payments.verified_date', $between)
            ->whereIn('enrollments.sales_executive_id', $employeeIds)
            ->selectRaw('enrollments.sales_executive_id as employee_id, SUM(payments.amount) as total')
            ->groupBy('enrollments.sales_executive_id')
            ->pluck('total', 'employee_id');

        $overdueNow = LeadFollowup::query()->whereIn('employee_id', $employeeIds)->where('is_done', false)->where('followup_date_time', '<', now())
            ->selectRaw('employee_id, COUNT(*) as total')->groupBy('employee_id')->pluck('total', 'employee_id');

        $targets = $this->targets();

        return collect($employeeIds)->mapWithKeys(function (int $id) use ($assignedCount, $convertedCount, $calls, $responseRows, $followUps, $revenue, $overdueNow, $workingDays, $targets): array {
            $mine = $responseRows->where('current_owner_id', $id);
            $minutes = $mine->filter(fn ($row): bool => $row->first_call !== null)
                ->map(fn ($row): int => max(0, (int) Carbon::parse($row->assigned_at)->diffInMinutes(Carbon::parse($row->first_call))))
                ->sort()
                ->values();
            $median = $minutes->isEmpty() ? null : $minutes->get(intdiv($minutes->count() - 1, 2));

            $due = $followUps->where('employee_id', $id);
            $kept = $due->filter(fn ($followUp): bool => $followUp->is_done
                && $followUp->updated_at->lessThanOrEqualTo($followUp->followup_date_time->copy()->addMinutes(self::FOLLOWUP_GRACE_MINUTES)))->count();

            $assignedTotal = (int) ($assignedCount[$id] ?? 0);
            $convertedTotal = (int) ($convertedCount[$id] ?? 0);
            $callTotal = (int) ($calls[$id] ?? 0);
            $callsPerDay = round($callTotal / $workingDays, 1);
            $keptPercent = $due->count() > 0 ? round($kept / $due->count() * 100) : null;
            $conversion = $assignedTotal > 0 ? round($convertedTotal / $assignedTotal * 100, 1) : null;

            return [$id => [
                'assigned' => $assignedTotal,
                'uncalled' => $mine->filter(fn ($row): bool => $row->first_call === null)->count(),
                'calls' => $callTotal,
                'calls_per_day' => $callsPerDay,
                'first_call_minutes' => $median,
                'followups_due' => $due->count(),
                'followups_kept' => $kept,
                'followup_on_time' => $keptPercent,
                'converted' => $convertedTotal,
                'conversion' => $conversion,
                'revenue' => (float) ($revenue[$id] ?? 0),
                'overdue_now' => (int) ($overdueNow[$id] ?? 0),
                'status' => [
                    'calls_per_day' => $this->grade($callsPerDay, $targets['calls_per_day'], true),
                    'first_call_minutes' => $median === null ? null : $this->grade($median, $targets['first_call_minutes'], false),
                    'followup_on_time' => $keptPercent === null ? null : $this->grade($keptPercent, $targets['followup_on_time'], true),
                    'conversion' => $conversion === null ? null : $this->grade($conversion, $targets['conversion'], true),
                ],
            ]];
        });
    }

    /**
     * Totals for a group of rows produced by forEmployees().
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function summarise(Collection $rows, Carbon $start, Carbon $end): array
    {
        $assigned = (int) $rows->sum('assigned');
        $converted = (int) $rows->sum('converted');
        $due = (int) $rows->sum('followups_due');
        $kept = (int) $rows->sum('followups_kept');
        $callsPerDay = $rows->isEmpty() ? 0.0 : round($rows->sum('calls') / $this->workingDays($start, $end) / $rows->count(), 1);

        return [
            'people' => $rows->count(),
            'assigned' => $assigned,
            'calls' => (int) $rows->sum('calls'),
            'calls_per_day' => $callsPerDay,
            'converted' => $converted,
            'conversion' => $assigned > 0 ? round($converted / $assigned * 100, 1) : null,
            'followup_on_time' => $due > 0 ? round($kept / $due * 100) : null,
            'revenue' => (float) $rows->sum('revenue'),
            'overdue_now' => (int) $rows->sum('overdue_now'),
            'uncalled' => (int) $rows->sum('uncalled'),
        ];
    }

    /**
     * Calls and newly assigned leads for each of the last weeks, oldest first.
     *
     * @return list<array{label: string, calls: int, assigned: int}>
     */
    public function weekly(int $employeeId, int $weeks = 6): array
    {
        $first = now()->startOfWeek()->subWeeks($weeks - 1);
        $calls = LeadFeedback::query()->where('employee_id', $employeeId)->where('created_at', '>=', $first)->get(['created_at']);
        $assigned = Lead::query()->where('current_owner_id', $employeeId)->where('assigned_at', '>=', $first)->get(['assigned_at']);

        $rows = [];
        for ($week = 0; $week < $weeks; $week++) {
            $from = $first->copy()->addWeeks($week);
            $to = $from->copy()->endOfWeek();
            $rows[] = [
                'label' => $from->format('M j'),
                'calls' => $calls->filter(fn ($row): bool => $row->created_at->between($from, $to))->count(),
                'assigned' => $assigned->filter(fn ($row): bool => $row->assigned_at->between($from, $to))->count(),
            ];
        }

        return $rows;
    }

    /**
     * How well a value meets its target: met, near (within a fifth) or below.
     */
    public function grade(float|int $value, float|int $target, bool $higherIsBetter): string
    {
        if ($target <= 0) {
            return 'met';
        }

        if ($higherIsBetter) {
            return $value >= $target ? 'met' : ($value >= $target * 0.8 ? 'near' : 'below');
        }

        return $value <= $target ? 'met' : ($value <= $target * 1.5 ? 'near' : 'below');
    }

    /**
     * Figures for Accounts: how many payments were decided, how fast, and what is waiting.
     *
     * @return array<string, mixed>
     */
    public function accounts(Carbon $start, Carbon $end): array
    {
        $decided = Payment::query()->whereIn('status', ['Verified', 'Rejected'])->whereBetween('verified_date', [$start, $end])->get(['status', 'amount', 'created_at', 'verified_date']);
        $verified = $decided->where('status', 'Verified');
        $hours = $decided->map(fn ($payment): float => max(0, $payment->created_at->diffInMinutes($payment->verified_date) / 60))->sort()->values();

        return [
            'verified_count' => $verified->count(),
            'verified_amount' => (float) $verified->sum('amount'),
            'rejected_count' => $decided->where('status', 'Rejected')->count(),
            'rejection_rate' => $decided->isNotEmpty() ? round($decided->where('status', 'Rejected')->count() / $decided->count() * 100, 1) : null,
            'median_hours' => $hours->isEmpty() ? null : round($hours->get(intdiv($hours->count() - 1, 2)), 1),
            'pending' => Payment::query()->where('status', 'Pending')->count(),
            'oldest_pending' => Payment::query()->where('status', 'Pending')->min('created_at'),
        ];
    }
}
