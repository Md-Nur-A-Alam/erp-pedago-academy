<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->employeeWithRole($request);
        $period = $this->reportingPeriod($request, 'this_month');
        $between = [$period['start'], $period['end']];

        $created = Lead::query()->whereBetween('created_at', $between);
        $totalLeads = (clone $created)->count();
        $convertedLeads = (clone $created)->where('status', 'Converted')->count();

        $assignedInPeriod = fn (Builder $leads) => $leads->whereBetween('assigned_at', $between);
        $convertedInPeriod = fn (Builder $leads) => $leads->whereBetween('assigned_at', $between)->where('status', 'Converted');

        $executives = Employee::query()
            ->where('role', 'Sales Executive')
            ->with('team:id,name')
            ->withCount([
                'ownedLeads as assigned_count' => $assignedInPeriod,
                'ownedLeads as converted_count' => $convertedInPeriod,
                'leadFeedbacks as calls_count' => fn (Builder $feedbacks) => $feedbacks->whereBetween('created_at', $between),
                'leadFollowups as overdue_count' => fn (Builder $followups) => $followups
                    ->where('is_done', false)
                    ->where('followup_date_time', '<', now()),
            ])
            ->orderByDesc('converted_count')
            ->orderByDesc('assigned_count')
            ->orderBy('name')
            ->get()
            ->filter(fn (Employee $executive): bool => $executive->status === 'Active' || $executive->assigned_count > 0)
            ->values();

        $campaigns = LeadSource::query()
            ->withCount([
                'leads as leads_count' => fn (Builder $leads) => $leads->whereBetween('created_at', $between),
                'leads as converted_count' => fn (Builder $leads) => $leads->whereBetween('created_at', $between)->where('status', 'Converted'),
            ])
            ->orderByDesc('leads_count')
            ->get()
            ->where('leads_count', '>', 0)
            ->values();

        $teams = Team::query()
            ->withCount([
                'members as members_count' => fn (Builder $members) => $members->where('status', 'Active'),
                'leads as assigned_count' => $assignedInPeriod,
                'leads as converted_count' => $convertedInPeriod,
            ])
            ->orderByDesc('assigned_count')
            ->orderBy('name')
            ->get();

        return view('reports.index', [
            'period' => $period,
            'summary' => [
                'total_leads' => $totalLeads,
                'assigned_leads' => (clone $created)->whereNotNull('current_owner_id')->count(),
                'converted_leads' => $convertedLeads,
                'conversion_rate' => $totalLeads > 0 ? round($convertedLeads / $totalLeads * 100, 1) : 0.0,
                'collected' => (float) Payment::query()
                    ->where('status', 'Verified')
                    ->whereBetween('verified_date', $between)
                    ->sum('amount'),
            ],
            'pipeline' => (clone $created)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'executives' => $executives,
            'campaigns' => $campaigns,
            'teams' => $teams,
        ]);
    }
}
