<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

abstract class Controller
{
    /**
     * The signed-in employee, provided their role is one of those allowed.
     *
     * @param  list<string>  $roles
     */
    protected function employeeWithRole(Request $request, array $roles = ['Admin']): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless($employee instanceof Employee && in_array($employee->role, $roles, true), 403);

        return $employee;
    }

    /**
     * Resolve the preset or custom reporting period from the request.
     *
     * @return array{range: string, start: Carbon, end: Carbon, label: string}
     */
    protected function reportingPeriod(Request $request, string $default = 'today'): array
    {
        $validated = $request->validate([
            'range' => ['sometimes', 'in:today,this_month,three_months,this_year,custom'],
            'start_date' => ['exclude_unless:range,custom', 'required', 'date_format:Y-m-d'],
            'end_date' => ['exclude_unless:range,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $range = $validated['range'] ?? $default;
        $today = now();

        [$start, $end] = match ($range) {
            'this_month' => [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->endOfDay()],
            'three_months' => [$today->copy()->subMonths(3)->startOfDay(), $today->copy()->endOfDay()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfDay()],
            'custom' => [
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
            ],
            default => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        };

        return [
            'range' => $range,
            'start' => $start,
            'end' => $end,
            'label' => $start->isSameDay($end)
                ? $start->format('M j, Y')
                : $start->format('M j, Y').' – '.$end->format('M j, Y'),
        ];
    }
}
