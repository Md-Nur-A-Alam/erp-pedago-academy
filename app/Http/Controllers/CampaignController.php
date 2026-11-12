<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseTopic;
use App\Models\Employee;
use App\Models\LeadSource;
use App\Models\Subject;
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
            'subject' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'topic' => ['nullable', 'integer', Rule::exists('course_topics', 'id')],
            'mode' => ['nullable', Rule::in(LeadSource::DISTRIBUTION_MODES)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'created' => ['nullable', Rule::in(['today', '7days', '30days'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'leads_desc', 'leads_asc'])],
        ]);
        $sort = $filters['sort'] ?? 'newest';
        $namedCampaigns = LeadSource::query()->whereNotNull('campaign_name');
        $search = trim($filters['search'] ?? '');
        $subject = isset($filters['subject']) ? Subject::query()->find($filters['subject']) : null;
        $topic = isset($filters['topic']) ? CourseTopic::query()->find($filters['topic']) : null;
        $createdLabels = [
            'today' => 'Today',
            '7days' => 'Last 7 days',
            '30days' => 'Last 30 days',
        ];
        $activeFilters = [];

        if ($search !== '') {
            $activeFilters[] = ['key' => 'search', 'label' => 'Search: '.$search];
        }

        if (filled($filters['channel'] ?? null)) {
            $activeFilters[] = ['key' => 'channel', 'label' => 'Channel: '.LeadSource::CHANNELS[$filters['channel']]];
        }

        if ($subject !== null) {
            $activeFilters[] = ['key' => 'subject', 'label' => 'Subject: '.$subject->name];
        }

        if ($topic !== null) {
            $activeFilters[] = ['key' => 'topic', 'label' => 'Topic: '.$topic->name];
        }

        if (filled($filters['mode'] ?? null)) {
            $activeFilters[] = ['key' => 'mode', 'label' => 'Mode: '.$filters['mode']];
        }

        if (filled($filters['status'] ?? null)) {
            $activeFilters[] = ['key' => 'status', 'label' => 'Status: '.ucfirst($filters['status'])];
        }

        if (filled($filters['created'] ?? null)) {
            $activeFilters[] = ['key' => 'created', 'label' => 'Created: '.$createdLabels[$filters['created']]];
        }

        return view('campaigns.index', [
            'campaigns' => (clone $namedCampaigns)
                ->when(
                    $search !== '',
                    function (EloquentBuilder $query) use ($search): void {
                        $query->where(function (EloquentBuilder $query) use ($search): void {
                            $query->where('campaign_name', 'like', '%'.$search.'%');

                            if (ctype_digit($search)) {
                                $query->orWhere('source_id', (int) $search);
                            }
                        });
                    },
                )
                ->when(
                    filled($filters['channel'] ?? null),
                    fn (EloquentBuilder $query) => $query->where('channel', $filters['channel']),
                )
                ->when(
                    isset($filters['subject']),
                    fn (EloquentBuilder $query) => $query->whereHas(
                        'course.subject',
                        fn (EloquentBuilder $courseQuery) => $courseQuery->whereKey($filters['subject']),
                    ),
                )
                ->when(
                    isset($filters['topic']),
                    fn (EloquentBuilder $query) => $query->whereHas(
                        'course.topic',
                        fn (EloquentBuilder $courseQuery) => $courseQuery->whereKey($filters['topic']),
                    ),
                )
                ->when(
                    filled($filters['mode'] ?? null),
                    fn (EloquentBuilder $query) => $query->where('distribution_mode', $filters['mode']),
                )
                ->when(
                    filled($filters['status'] ?? null),
                    fn (EloquentBuilder $query) => $query->where('is_active', $filters['status'] === 'active'),
                )
                ->when(
                    filled($filters['created'] ?? null),
                    function (EloquentBuilder $query) use ($filters): void {
                        $days = $filters['created'] === 'today' ? 0 : (int) $filters['created'];
                        $query->where('created_at', '>=', now()->subDays($days)->startOfDay());
                    },
                )
                ->with(['createdBy', 'editedBy', 'course.subject', 'course.topic'])
                ->withCount('leads')
                ->when(
                    in_array($sort, ['leads_desc', 'leads_asc'], true),
                    fn (EloquentBuilder $query) => $query->orderBy('leads_count', $sort === 'leads_asc' ? 'asc' : 'desc'),
                    fn (EloquentBuilder $query) => $query->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc'),
                )
                ->orderBy('source_id', $sort === 'oldest' ? 'asc' : 'desc')
                ->paginate(25)
                ->withQueryString(),
            'channels' => LeadSource::CHANNELS,
            'distributionModes' => LeadSource::DISTRIBUTION_MODES,
            'courses' => Course::query()
                ->with(['subject:id,name', 'topic:id,name,subject_id'])
                ->orderBy('title')
                ->get(['id', 'title', 'code', 'subject_id', 'course_topic_id']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'topics' => CourseTopic::query()
                ->orderBy('name')
                ->get(['id', 'name', 'subject_id']),
            'stats' => [
                'total' => (clone $namedCampaigns)->count(),
                'active' => (clone $namedCampaigns)->where('is_active', true)->count(),
                'auto' => (clone $namedCampaigns)->where('distribution_mode', 'Auto')->count(),
                'manual' => (clone $namedCampaigns)->where('distribution_mode', 'Manual')->count(),
                'unlinked' => (clone $namedCampaigns)
                    ->where(function (EloquentBuilder $query): void {
                        $query->whereNull('course_id')
                            ->orWhereHas('course', fn (EloquentBuilder $courseQuery) => $courseQuery->whereNull('subject_id'));
                    })
                    ->count(),
            ],
            'activeFilters' => $activeFilters,
            'filters' => [
                'search' => $search,
                'channel' => $filters['channel'] ?? '',
                'subject' => $filters['subject'] ?? '',
                'topic' => $filters['topic'] ?? '',
                'mode' => $filters['mode'] ?? '',
                'status' => $filters['status'] ?? '',
                'created' => $filters['created'] ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->employeeFor($request);

        return redirect()->route('campaigns.index', ['create' => 1]);
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
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
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
            'courses' => Course::query()
                ->with(['subject:id,name', 'topic:id,name,subject_id'])
                ->orderBy('title')
                ->get(['id', 'title', 'code', 'subject_id', 'course_topic_id']),
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
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'distribution_mode' => ['required', Rule::in(LeadSource::DISTRIBUTION_MODES)],
            'is_active' => ['sometimes', 'boolean'],
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
