<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;

/**
 * What each kind of leave still has to give an employee, following the Bangladesh Labour Act 2006:
 * casual (s.115), sick (s.116), annual or earned (s.117) and maternity (s.46) leave. The yearly
 * figures are settings, so a company that grants more than the Act can raise them.
 */
class LeaveBalance
{
    /**
     * @return array<string, array{entitled: ?int, used: int, pending: int, remaining: ?int, basis: string}>
     */
    public function for(Employee $employee, ?Carbon $on = null): array
    {
        $on ??= today();
        $year = $on->copy()->startOfYear();
        $requests = $employee->leaveRequests()->whereIn('status', ['Approved', 'Pending'])->get();

        $sum = fn (string $type, string $status, bool $thisYearOnly): int => (int) $requests
            ->where('type', $type)
            ->where('status', $status)
            ->filter(fn (LeaveRequest $leave): bool => ! $thisYearOnly || $leave->start_date->gte($year))
            ->sum('days');

        $balances = [];

        foreach (['Casual' => 'leave_casual_days', 'Sick' => 'leave_sick_days'] as $type => $setting) {
            $entitled = SystemSetting::integer($setting);
            $used = $sum($type, 'Approved', true);
            $pending = $sum($type, 'Pending', true);
            $balances[$type] = [
                'entitled' => $entitled,
                'used' => $used,
                'pending' => $pending,
                'remaining' => max(0, $entitled - $used - $pending),
                'basis' => $type === 'Casual' ? 'Section 115 · a calendar year' : 'Section 116 · a calendar year',
            ];
        }

        $earned = $this->earnedAnnualDays($employee, $on);
        $annualUsed = $sum('Annual', 'Approved', false);
        $annualPending = $sum('Annual', 'Pending', false);
        $balances['Annual'] = [
            'entitled' => $earned,
            'used' => $annualUsed,
            'pending' => $annualPending,
            'remaining' => max(0, min($earned - $annualUsed - $annualPending, SystemSetting::integer('leave_earned_max_days'))),
            'basis' => $this->serviceYears($employee, $on) < 1
                ? 'Section 117 · usable after 1 year of service'
                : 'Section 117 · 1 day for every '.SystemSetting::integer('leave_earned_work_days').' days worked',
        ];

        $maternityEligible = $this->serviceMonths($employee, $on) >= 6;
        $maternityEntitled = $maternityEligible ? SystemSetting::integer('leave_maternity_days') : 0;
        $maternityUsed = $sum('Maternity', 'Approved', true);
        $maternityPending = $sum('Maternity', 'Pending', true);
        $balances['Maternity'] = [
            'entitled' => $maternityEntitled,
            'used' => $maternityUsed,
            'pending' => $maternityPending,
            'remaining' => max(0, $maternityEntitled - $maternityUsed - $maternityPending),
            'basis' => $maternityEligible ? 'Section 46 · 16 weeks, women employees' : 'Section 46 · needs 6 months of service',
        ];

        $balances['Unpaid'] = [
            'entitled' => null,
            'used' => $sum('Unpaid', 'Approved', true),
            'pending' => $sum('Unpaid', 'Pending', true),
            'remaining' => null,
            'basis' => 'No pay, no limit on the total',
        ];

        return $balances;
    }

    /**
     * Annual leave earned so far: one day for every set number of days worked, counting Fridays as the weekly holiday.
     */
    public function earnedAnnualDays(Employee $employee, ?Carbon $on = null): int
    {
        $on ??= today();

        if ($this->serviceYears($employee, $on) < 1) {
            return 0;
        }

        $workedDays = intdiv((int) $this->joinedOn($employee)->diffInDays($on) * 6, 7);

        return intdiv($workedDays, max(1, SystemSetting::integer('leave_earned_work_days')));
    }

    private function joinedOn(Employee $employee): Carbon
    {
        return Carbon::parse($employee->joining_date ?? $employee->created_at ?? today())->startOfDay();
    }

    private function serviceYears(Employee $employee, Carbon $on): int
    {
        return (int) $this->joinedOn($employee)->diffInYears($on);
    }

    private function serviceMonths(Employee $employee, Carbon $on): int
    {
        return (int) $this->joinedOn($employee)->diffInMonths($on);
    }
}
