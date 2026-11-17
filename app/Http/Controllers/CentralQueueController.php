<?php

namespace App\Http\Controllers;

use App\Actions\DistributeLeads;
use App\Models\Employee;
use App\Models\LeadSource;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CentralQueueController extends Controller
{
    public function index(Request $request, DistributeLeads $engine): View
    {
        $this->employeeWithRole($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'source_id' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in(LeadSource::DISTRIBUTION_MODES)],
        ]);
        $search = trim($filters['search'] ?? '');

        $leads = $engine->queueQuery()
            ->with(['source:source_id,channel,campaign_name,distribution_mode,is_active', 'interestedCourse:id,title'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->where('student_name', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhere('id', (int) ltrim(strtoupper($search), 'L-'));
            }))
            ->when(filled($filters['source_id'] ?? null), fn (Builder $query) => $query->where('source_id', $filters['source_id']))
            ->when(filled($filters['mode'] ?? null), fn (Builder $query) => $query->whereHas(
                'source',
                fn (Builder $source) => $source->where('distribution_mode', $filters['mode']),
            ))
            ->paginate(25)
            ->withQueryString();

        $executives = $engine->executivesWithAvailability();
        $available = $engine->availableExecutives($executives);
        $queueTotal = $engine->queueQuery()->count();
        $autoTotal = $engine->queueQuery(autoOnly: true)->count();

        return view('central-queue.index', [
            'leads' => $leads,
            'filters' => $filters,
            'plan' => $engine->plan($available),
            'executives' => $executives,
            'availableCount' => $available->count(),
            'queueTotal' => $queueTotal,
            'autoTotal' => $autoTotal,
            'manualTotal' => $queueTotal - $autoTotal,
            'oldestLead' => $engine->queueQuery()->reorder('created_at')->first(),
            'engineEnabled' => SystemSetting::enabled('distribution_engine_enabled'),
            'campaigns' => LeadSource::query()
                ->whereIn('source_id', $engine->queueQuery()->reorder()->select('source_id'))
                ->orderBy('campaign_name')
                ->get(['source_id', 'channel', 'campaign_name']),
        ]);
    }

    public function distribute(Request $request, DistributeLeads $engine): RedirectResponse
    {
        $admin = $this->employeeWithRole($request);
        $result = $engine->run($admin);

        if ($result['leads'] === 0) {
            return back()->with('status', 'Nothing was distributed: the queue has no auto-distributed leads or no executive is available.');
        }

        return back()->with('status', "Distributed {$result['leads']} leads in {$result['batches']} batches.");
    }

    public function assign(Request $request, DistributeLeads $engine): RedirectResponse
    {
        $admin = $this->employeeWithRole($request);
        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1', 'max:100'],
            'lead_ids.*' => ['integer', 'distinct'],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('status', 'Active')->where('role', 'Sales Executive'),
            ],
        ], [
            'lead_ids.required' => 'Select at least one lead to assign.',
            'employee_id.required' => 'Choose the sales executive who should receive the selected leads.',
        ]);

        $executive = Employee::query()->with(['team', 'teamsLed'])->findOrFail($validated['employee_id']);
        $leads = $engine->queueQuery()->whereIn('id', $validated['lead_ids'])->get();

        if ($leads->isEmpty()) {
            return back()->withErrors(['lead_ids' => 'The selected leads are no longer in the queue.']);
        }

        $engine->assignBatch($executive, $leads, $admin);
        $skipped = count($validated['lead_ids']) - $leads->count();

        return back()->with(
            'status',
            "Assigned {$leads->count()} ".($leads->count() === 1 ? 'lead' : 'leads')." to {$executive->name}."
                .($skipped > 0 ? " {$skipped} had already left the queue." : ''),
        );
    }
}
