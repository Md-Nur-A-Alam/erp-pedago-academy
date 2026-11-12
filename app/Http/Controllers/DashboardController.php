<?php

namespace App\Http\Controllers;

use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\EmployeeRoster;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->employee instanceof Employee, 403);

        $validated = $request->validate([
            'range' => ['sometimes', 'in:today,this_month,three_months,this_year,custom'],
            'start_date' => ['exclude_unless:range,custom', 'required', 'date_format:Y-m-d'],
            'end_date' => ['exclude_unless:range,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $range = $validated['range'] ?? 'today';
        $today = now();

        [$rangeStart, $rangeEnd] = match ($range) {
            'this_month' => [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->endOfDay()],
            'three_months' => [$today->copy()->subMonths(3)->startOfDay(), $today->copy()->endOfDay()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfDay()],
            'custom' => [
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
            ],
            default => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        };
        $rangeLabel = $rangeStart->isSameDay($rangeEnd)
            ? $rangeStart->format('M j, Y')
            : $rangeStart->format('M j, Y').' – '.$rangeEnd->format('M j, Y');

        $employee = $user->employee;
        $data = [
            'employee' => $employee,
            'range' => $range,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'rangeLabel' => $rangeLabel,
        ];

        if ($employee->isAdmin()) {
            $leadsInRange = Lead::query()->whereBetween('created_at', [$rangeStart, $rangeEnd]);
            $pipeline = (clone $leadsInRange)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $data += [
                'metrics' => [
                    'total_leads' => (clone $leadsInRange)->count(),
                    'unassigned_leads' => Lead::query()
                        ->where('status', 'New')
                        ->whereNull('current_owner_id')
                        ->count(),
                    'active_batches' => LeadBatch::query()
                        ->where('status', 'Active')
                        ->whereBetween('assigned_at', [$rangeStart, $rangeEnd])
                        ->count(),
                    'overdue_followups' => LeadFollowup::query()
                        ->where('is_done', false)
                        ->where('followup_date_time', '<', now())
                        ->whereBetween('followup_date_time', [$rangeStart, $rangeEnd])
                        ->count(),
                    'payments_pending' => Payment::query()
                        ->where('status', 'Pending')
                        ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                        ->count(),
                    'transfers_pending' => LeadTransfer::query()
                        ->where('status', 'Pending')
                        ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                        ->count(),
                    'duplicate_reviews' => DuplicateLeadReview::query()
                        ->where('status', 'Pending Review')
                        ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                        ->count(),
                ],
                'pipeline' => $pipeline,
                'roster' => EmployeeRoster::query()
                    ->with([
                        'shift:id,shift_start_time,shift_end_time',
                        'employee' => fn ($query) => $query
                            ->select('id', 'name', 'role')
                            ->withCount([
                                'batches as active_batches_count' => fn ($batches) => $batches->where('status', 'Active'),
                                'leadFollowups as overdue_followups_count' => fn ($followups) => $followups
                                    ->where('is_done', false)
                                    ->where('followup_date_time', '<', now()),
                            ]),
                    ])
                    ->whereDate('roster_date', today())
                    ->where('is_on_duty', true)
                    ->orderBy('employee_id')
                    ->limit(8)
                    ->get(),
                'pendingChanges' => EmployeeChangeRequest::query()
                    ->where('status', 'Pending')
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->count(),
            ];
        }

        return view('dashboard', $data);
    }
}
