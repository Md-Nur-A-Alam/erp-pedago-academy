<?php

namespace App\Http\Controllers;

use App\Actions\PerformanceMetrics;
use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    private const ROLES = ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'MarketingExecutive', 'Accounts'];

    public function index(Request $request, PerformanceMetrics $metrics): View
    {
        $viewer = $this->employeeWithRole($request, self::ROLES);
        $period = $this->reportingPeriod($request, 'this_month');
        $data = ['viewer' => $viewer, 'period' => $period, 'targets' => $metrics->targets()];

        if ($viewer->isSalesPerson()) {
            $mine = $metrics->forEmployees([$viewer->id], $period['start'], $period['end'])->get($viewer->id);
            $data += ['mine' => $mine, 'weekly' => $metrics->weekly($viewer->id)];
        }

        if ($viewer->isAdmin() || in_array($viewer->role, ['Supervisor', 'Team Leader'], true)) {
            $ids = $viewer->managedExecutiveIds() ?? Employee::query()->whereIn('role', Employee::SALES_ROLES)->where('status', 'Active')->pluck('id')->all();
            $rows = $metrics->forEmployees($ids, $period['start'], $period['end']);
            $people = Employee::query()->whereIn('id', $ids)->with(['team:id,name', 'teamsLed:id,name,team_leader_id'])->get()->keyBy('id');

            $data += [
                'people' => $people,
                'rows' => $rows->sortByDesc(fn (array $row): array => [$row['converted'], $row['calls']])->all(),
                'summary' => $metrics->summarise($rows, $period['start'], $period['end']),
                'ownIsInTeam' => $viewer->role === 'Team Leader',
            ];
        }

        if ($viewer->isAdmin() || $viewer->role === 'Accounts') {
            $data['accounts'] = $metrics->accounts($period['start'], $period['end']);
        }

        if ($viewer->isAdmin() || $viewer->role === 'MarketingExecutive') {
            $between = [$period['start'], $period['end']];
            $received = Lead::query()->whereBetween('created_at', $between)->count();
            $duplicates = DuplicateLeadReview::query()->whereBetween('created_at', $between)->count();
            $data['marketing'] = [
                'received' => $received,
                'converted' => Lead::query()->whereBetween('created_at', $between)->where('status', 'Converted')->count(),
                'duplicates' => $duplicates,
                'duplicate_share' => $received + $duplicates > 0 ? round($duplicates / ($received + $duplicates) * 100, 1) : null,
                'uncalled' => Lead::query()->whereBetween('created_at', $between)->whereNotIn('status', ['New'])->whereDoesntHave('feedbacks')->count(),
                'waiting' => Lead::query()->whereBetween('created_at', $between)->where('status', 'New')->whereNull('current_owner_id')->count(),
                'campaigns' => LeadSource::query()
                    ->withCount([
                        'leads as leads_count' => fn ($leads) => $leads->whereBetween('created_at', $between),
                        'leads as converted_count' => fn ($leads) => $leads->whereBetween('created_at', $between)->where('status', 'Converted'),
                        'leads as waiting_count' => fn ($leads) => $leads->whereBetween('created_at', $between)->where('status', 'New')->whereNull('current_owner_id'),
                    ])
                    ->orderByDesc('leads_count')
                    ->limit(15)
                    ->get()
                    ->where('leads_count', '>', 0)
                    ->values(),
            ];
        }

        $data['sections'] = array_values(array_filter([
            isset($data['mine']) ? 'mine' : null,
            isset($data['rows']) ? 'team' : null,
            isset($data['marketing']) ? 'marketing' : null,
            isset($data['accounts']) ? 'accounts' : null,
        ]));

        return view('performance.index', $data);
    }
}
