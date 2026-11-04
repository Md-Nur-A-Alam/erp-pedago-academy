<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $this->employeeFor($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'channel' => ['nullable', Rule::in(array_keys(LeadSource::CHANNELS))],
            'sort' => ['nullable', Rule::in(['leads_desc', 'leads_asc'])],
        ]);
        $sort = $filters['sort'] ?? 'leads_desc';

        return view('campaigns.index', [
            'campaigns' => LeadSource::query()
                ->whereNotNull('campaign_name')
                ->when(
                    filled($filters['search'] ?? null),
                    fn (EloquentBuilder $query) => $query->where('campaign_name', 'like', '%'.trim($filters['search']).'%'),
                )
                ->when(
                    filled($filters['channel'] ?? null),
                    fn (EloquentBuilder $query) => $query->where('channel', $filters['channel']),
                )
                ->with('createdBy')
                ->withCount('leads')
                ->orderBy('leads_count', $sort === 'leads_asc' ? 'asc' : 'desc')
                ->orderBy('source_id')
                ->paginate(15)
                ->withQueryString(),
            'channels' => LeadSource::CHANNELS,
            'distributionModes' => LeadSource::DISTRIBUTION_MODES,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'channel' => $filters['channel'] ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->employeeFor($request);

        return redirect(route('campaigns.index').'#campaign-create');
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employeeFor($request);
        $request->merge([
            'campaign_name' => trim((string) $request->input('campaign_name', '')),
        ]);

        $validated = $request->validate([
            'campaign_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('leads_source', 'campaign_name')
                    ->where(fn (Builder $query) => $query->where('channel', $request->input('channel'))),
            ],
            'channel' => ['required', Rule::in(array_keys(LeadSource::CHANNELS))],
            'distribution_mode' => ['required', Rule::in(LeadSource::DISTRIBUTION_MODES)],
        ]);

        LeadSource::query()->create([
            ...$validated,
            'is_active' => true,
            'created_by' => $employee->id,
            'edited_by' => $employee->id,
        ]);

        return redirect()->route('campaigns.index')
            ->with('status', 'Campaign created successfully.');
    }

    public function edit(Request $request, LeadSource $campaign): View
    {
        $this->employeeFor($request);
        abort_if($campaign->campaign_name === null, 404);

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'channels' => LeadSource::CHANNELS,
            'distributionModes' => LeadSource::DISTRIBUTION_MODES,
        ]);
    }

    public function update(Request $request, LeadSource $campaign): RedirectResponse
    {
        $employee = $this->employeeFor($request);
        abort_if($campaign->campaign_name === null, 404);
        $request->merge([
            'campaign_name' => trim((string) $request->input('campaign_name', '')),
        ]);

        $validated = $request->validate([
            'campaign_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('leads_source', 'campaign_name')
                    ->where(fn (Builder $query) => $query->where('channel', $request->input('channel')))
                    ->ignore($campaign->source_id, 'source_id'),
            ],
            'channel' => ['required', Rule::in(array_keys(LeadSource::CHANNELS))],
            'distribution_mode' => ['required', Rule::in(LeadSource::DISTRIBUTION_MODES)],
        ]);

        $campaign->update([
            ...$validated,
            'edited_by' => $employee->id,
        ]);

        return redirect()->route('campaigns.index')
            ->with('status', 'Campaign updated successfully.');
    }

    public function destroy(Request $request, LeadSource $campaign): RedirectResponse
    {
        $this->employeeFor($request);
        abort_if($campaign->campaign_name === null, 404);

        $hasLeads = DB::transaction(function () use ($campaign): bool {
            $lockedCampaign = LeadSource::query()
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCampaign->leads()->exists()) {
                return true;
            }

            $lockedCampaign->delete();

            return false;
        });

        if ($hasLeads) {
            return back()->withErrors([
                'campaign' => 'This campaign cannot be deleted while it has leads.',
            ]);
        }

        return redirect()->route('campaigns.index')
            ->with('status', 'Campaign deleted successfully.');
    }

    private function employeeFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless(
            $employee instanceof Employee
                && in_array($employee->role, ['Admin', 'MarketingExecutive'], true),
            403,
        );

        return $employee;
    }
}
