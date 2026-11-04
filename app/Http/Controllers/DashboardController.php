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
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->employee instanceof Employee, 403);

        $employee = $user->employee;
        $data = ['employee' => $employee];

        if ($employee->isAdmin()) {
            $pipeline = Lead::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $data += [
                'metrics' => [
                    'unassigned_leads' => Lead::query()->where('status', 'New')->whereNull('current_owner_id')->count(),
                    'active_batches' => LeadBatch::query()->where('status', 'Active')->count(),
                    'overdue_followups' => LeadFollowup::query()
                        ->where('is_done', false)
                        ->where('followup_date_time', '<', now())
                        ->count(),
                    'payments_pending' => Payment::query()->where('status', 'Pending')->count(),
                    'transfers_pending' => LeadTransfer::query()->where('status', 'Pending')->count(),
                    'duplicate_reviews' => DuplicateLeadReview::query()->where('status', 'Pending Review')->count(),
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
                    ->count(),
            ];
        }

        return view('dashboard', $data);
    }
}
