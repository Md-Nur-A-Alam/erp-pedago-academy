<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseTopic;
use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadSource;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    /**
     * Display a listing of all leads with statistics and filters.
     */
    public function index(Request $request): View
    {
        $currentEmployee = $this->authenticatedEmployee($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'owner' => ['nullable', 'string', 'max:50'],
            'created' => ['nullable', Rule::in(['today', '7days', '30days'])],
            'channel' => ['nullable', Rule::in(array_keys(LeadSource::CHANNELS))],
            'campaign' => ['nullable', 'string', 'max:150'],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'topic_id' => ['nullable', 'integer', Rule::exists('course_topics', 'id')],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc', 'status', 'last_contact'])],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $sort = $filters['sort'] ?? 'newest';
        $search = trim($filters['search'] ?? '');

        // KPI Statistics
        $stats = [
            'total_leads' => Lead::query()->count(),
            'unassigned' => Lead::query()->whereNull('current_owner_id')->count(),
            'overdue_followups' => LeadFollowup::query()
                ->where('is_done', false)
                ->where('followup_date_time', '<', Carbon::now())
                ->count(),
            'follow_up' => Lead::query()->where('status', 'Follow-up')->count(),
            'converted' => Lead::query()->where('status', 'Converted')->count(),
        ];

        // Base Query
        $query = Lead::query()->with([
            'source',
            'interestedCourse.subject',
            'interestedCourse.topic',
            'currentOwner.team',
            'team',
            'latestPendingFollowup',
        ]);

        $this->applyFilters($query, $filters);

        // Sorting
        match ($sort) {
            'oldest' => $query->orderBy('id', 'asc'),
            'name_asc' => $query->orderBy('student_name', 'asc'),
            'name_desc' => $query->orderBy('student_name', 'desc'),
            'status' => $query->orderBy('status', 'asc')->orderBy('id', 'desc'),
            'last_contact' => $query->orderByRaw('last_action_at IS NULL, last_action_at DESC'),
            default => $query->orderBy('id', 'desc'),
        };

        $leads = $query->paginate(25)->withQueryString();

        // Active filters list for display tag pills
        $activeFilters = $this->buildActiveFilterTags($filters);

        // Sales persons for unassigned lead direct assignment dropdown: (id - name)
        $salesPersons = Employee::query()
            ->where('status', 'Active')
            ->whereIn('role', ['Sales Executive', 'Team Leader', 'Supervisor'])
            ->orderBy('office_id')
            ->get();

        // Datasets for filter options
        $teams = Team::query()->where('is_active', true)->orderBy('name')->get();
        $channels = LeadSource::CHANNELS;
        $campaigns = LeadSource::query()
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '<>', '')
            ->select('campaign_name')
            ->distinct()
            ->orderBy('campaign_name')
            ->pluck('campaign_name');
        $courses = Course::query()->where('is_active', true)->orderBy('title')->get();
        $subjects = Subject::query()->orderBy('name')->get();
        $topics = CourseTopic::query()->orderBy('name')->get();

        $canAssign = $currentEmployee->isAdmin() || $currentEmployee->role === 'MarketingExecutive';

        return view('leads.index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => $filters,
            'activeFilters' => $activeFilters,
            'sort' => $sort,
            'salesPersons' => $salesPersons,
            'teams' => $teams,
            'channels' => $channels,
            'campaigns' => $campaigns,
            'courses' => $courses,
            'subjects' => $subjects,
            'topics' => $topics,
            'canAssign' => $canAssign,
            'currentEmployee' => $currentEmployee,
        ]);
    }

    /**
     * Direct assignment of an unassigned lead to a sales person.
     */
    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        $currentEmployee = $this->authorizeAssignment($request);

        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('status', 'Active'),
            ],
        ]);

        $targetEmployee = Employee::query()->with('team')->findOrFail($validated['employee_id']);

        $lead->current_owner_id = $targetEmployee->id;
        $lead->team_id = $targetEmployee->team_id;
        $lead->assigned_by_employee_id = $currentEmployee->id;
        $lead->assigned_at = Carbon::now();
        $lead->edited_by = $currentEmployee->id;

        if ($lead->status === 'New') {
            $lead->status = 'Assigned';
        }

        $lead->save();

        return back()->with('status', "Lead L-{$lead->id} successfully assigned to ({$targetEmployee->office_id} - {$targetEmployee->name}).");
    }

    /**
     * Quick create a lead from the modal.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentEmployee = $this->authenticatedEmployee($request);

        abort_unless(
            $currentEmployee->isAdmin() || in_array($currentEmployee->role, ['MarketingExecutive', 'Team Leader', 'Supervisor'], true),
            403,
            'You are not authorized to create leads.',
        );

        foreach (['contact_number', 'whatsapp_number', 'alternative_contact_number'] as $phoneField) {
            if ($request->has($phoneField)) {
                $request->merge([
                    $phoneField => User::normalizePhoneNumber((string) $request->input($phoneField)),
                ]);
            }
        }

        $validated = $request->validate([
            'student_name' => ['required', 'string', 'max:150'],
            'contact_number' => ['required', 'string', 'max:25', new PhoneNumber],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'whatsapp_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
            'alternative_contact_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
            'email' => ['nullable', 'email', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'class' => ['nullable', 'string', 'max:50'],
            'student_parent_type' => ['nullable', 'string', 'max:30'],
            'student_background' => ['nullable', 'string'],
            'ad_set_id' => ['nullable', 'string', 'max:100'],
            'interested_course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'channel' => ['required', 'string', Rule::in(array_keys(LeadSource::CHANNELS))],
            'campaign_name' => ['nullable', 'string', 'max:150'],
            'assign_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('status', 'Active')],
        ]);

        $channel = $validated['channel'];
        $campaignName = filled($validated['campaign_name'] ?? null) ? trim($validated['campaign_name']) : null;
        $courseId = $validated['interested_course_id'] ?? null;
        $assignEmployeeId = $validated['assign_employee_id'] ?? null;

        unset($validated['channel'], $validated['campaign_name'], $validated['assign_employee_id']);

        $isDuplicate = DB::transaction(function () use ($validated, $channel, $campaignName, $courseId, $assignEmployeeId, $currentEmployee): bool {
            $source = LeadSource::query()->firstOrCreate(
                [
                    'channel' => $channel,
                    'campaign_name' => $campaignName,
                ],
                [
                    'course_id' => $courseId,
                    'created_by' => $currentEmployee->id,
                    'edited_by' => $currentEmployee->id,
                ],
            );

            $existingLead = Lead::query()->where('contact_number', $validated['contact_number'])->first();

            if ($existingLead !== null) {
                DuplicateLeadReview::query()->create([
                    'matched_lead_id' => $existingLead->id,
                    'source_id' => $source->source_id,
                    'student_name' => $validated['student_name'],
                    'contact_number' => $validated['contact_number'],
                    'guardian_name' => $validated['guardian_name'] ?? null,
                    'whatsapp_number' => $validated['whatsapp_number'] ?? null,
                    'alternative_contact_number' => $validated['alternative_contact_number'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'age' => $validated['age'] ?? null,
                    'class' => $validated['class'] ?? null,
                    'student_parent_type' => $validated['student_parent_type'] ?? null,
                    'student_background' => $validated['student_background'] ?? null,
                    'ad_set_id' => $validated['ad_set_id'] ?? null,
                    'interested_course_id' => $courseId,
                    'raw_payload' => [
                        ...$validated,
                        'channel' => $channel,
                        'campaign_name' => $campaignName,
                    ],
                    'match_criteria' => ['primary_phone' => $validated['contact_number']],
                    'created_at' => Carbon::now(),
                ]);

                return true;
            }

            $leadData = [
                ...$validated,
                'source_id' => $source->source_id,
                'status' => 'New',
                'created_at' => Carbon::now(),
            ];

            if ($assignEmployeeId !== null) {
                $assignee = Employee::query()->find($assignEmployeeId);
                if ($assignee !== null) {
                    $leadData['current_owner_id'] = $assignee->id;
                    $leadData['team_id'] = $assignee->team_id;
                    $leadData['assigned_by_employee_id'] = $currentEmployee->id;
                    $leadData['assigned_at'] = Carbon::now();
                    $leadData['status'] = 'Assigned';
                }
            }

            Lead::query()->create($leadData);

            return false;
        });

        if ($isDuplicate) {
            return redirect()->route('leads.index')
                ->with('status', 'A lead with this phone number already exists. The entry has been queued for duplicate review.');
        }

        return redirect()->route('leads.index')
            ->with('status', 'Lead created successfully.');
    }

    /**
     * Export filtered leads to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authenticatedEmployee($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
            'team_id' => ['nullable', 'integer'],
            'owner' => ['nullable', 'string', 'max:50'],
            'created' => ['nullable', Rule::in(['today', '7days', '30days'])],
            'channel' => ['nullable', 'string'],
            'campaign' => ['nullable', 'string'],
            'course_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'topic_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc', 'status', 'last_contact'])],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $query = Lead::query()->with([
            'source',
            'interestedCourse.subject',
            'interestedCourse.topic',
            'currentOwner.team',
            'team',
            'latestPendingFollowup',
        ]);

        $this->applyFilters($query, $filters);
        $query->orderBy('id', 'desc');

        $headers = [
            'Lead ID',
            'Student Name',
            'Guardian Name',
            'Contact Number',
            'WhatsApp Number',
            'Alternative Number',
            'Email',
            'Location',
            'Age',
            'Class',
            'Course',
            'Subject',
            'Topic',
            'Campaign',
            'Channel',
            'Status',
            'Assigned Owner',
            'Team',
            'Last Contact',
            'Follow-up Date',
            'Created At',
        ];

        $filename = 'pedago-leads-export-'.Carbon::now()->format('Ymd-His').'.csv';

        return response()->streamDownload(static function () use ($query, $headers): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $headers);

            $query->chunk(500, static function ($leads) use ($output): void {
                foreach ($leads as $lead) {
                    $ownerStr = $lead->currentOwner
                        ? "({$lead->currentOwner->office_id} - {$lead->currentOwner->name})"
                        : 'Unassigned';

                    $channelStr = $lead->source?->channel
                        ? (LeadSource::CHANNELS[$lead->source->channel] ?? ucfirst($lead->source->channel))
                        : '—';

                    $followupStr = $lead->latestPendingFollowup
                        ? ($lead->latestPendingFollowup->followup_date_time->format('Y-m-d H:i').($lead->latestPendingFollowup->followup_date_time->isPast() ? ' (OVERDUE)' : ''))
                        : '';

                    fputcsv($output, [
                        'L-'.$lead->id,
                        $lead->student_name,
                        $lead->guardian_name ?? '',
                        $lead->contact_number,
                        $lead->whatsapp_number ?? '',
                        $lead->alternative_contact_number ?? '',
                        $lead->email ?? '',
                        $lead->location ?? '',
                        $lead->age ?? '',
                        $lead->class ?? '',
                        $lead->interestedCourse?->title ?? '',
                        $lead->interestedCourse?->subject?->name ?? '',
                        $lead->interestedCourse?->topic?->name ?? '',
                        $lead->source?->campaign_name ?? '',
                        $channelStr,
                        $lead->status,
                        $ownerStr,
                        $lead->team?->name ?? $lead->currentOwner?->team?->name ?? '',
                        $lead->last_action_at ? $lead->last_action_at->format('Y-m-d H:i') : '',
                        $followupStr,
                        $lead->created_at->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Apply all search and filter conditions to the lead query.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(EloquentBuilder $query, array $filters): void
    {
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $cleanCode = preg_replace('/^L-?/i', '', $search);
            $numericId = is_numeric($cleanCode) ? (int) $cleanCode : null;

            $query->where(static function (EloquentBuilder $q) use ($search, $numericId): void {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%")
                    ->orWhere('alternative_contact_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if ($numericId !== null) {
                    $q->orWhere('id', $numericId);
                }
            });
        }

        if (! empty($filters['overdue'])) {
            $query->whereHas('followups', static fn (EloquentBuilder $q): EloquentBuilder => $q->where('is_done', false)->where('followup_date_time', '<', Carbon::now()));
        }

        if (filled($filters['status'] ?? null)) {
            $status = $filters['status'];
            if ($status === 'overdue') {
                $query->whereHas('followups', static fn (EloquentBuilder $q): EloquentBuilder => $q->where('is_done', false)->where('followup_date_time', '<', Carbon::now()));
            } elseif ($status === 'Pending payment') {
                $query->whereIn('status', ['Enrolled - Pending Payment', 'Payment Under Review']);
            } elseif ($status === 'Not interested') {
                $query->whereIn('status', ['Not Interested', 'Unreachable']);
            } else {
                $query->where('status', $status);
            }
        }

        if (filled($filters['team_id'] ?? null)) {
            $query->where('team_id', (int) $filters['team_id']);
        }

        if (filled($filters['owner'] ?? null)) {
            $owner = $filters['owner'];
            if ($owner === 'unassigned') {
                $query->whereNull('current_owner_id');
            } elseif (is_numeric($owner)) {
                $query->where('current_owner_id', (int) $owner);
            }
        }

        if (filled($filters['created'] ?? null)) {
            match ($filters['created']) {
                'today' => $query->whereDate('created_at', Carbon::today()),
                '7days' => $query->where('created_at', '>=', Carbon::now()->subDays(7)),
                '30days' => $query->where('created_at', '>=', Carbon::now()->subDays(30)),
                default => null,
            };
        }

        if (filled($filters['channel'] ?? null)) {
            $channel = $filters['channel'];
            $query->whereHas('source', static fn (EloquentBuilder $q): EloquentBuilder => $q->where('channel', $channel));
        }

        if (filled($filters['campaign'] ?? null)) {
            $campaign = $filters['campaign'];
            $query->whereHas('source', static fn (EloquentBuilder $q): EloquentBuilder => $q->where('campaign_name', $campaign));
        }

        if (filled($filters['course_id'] ?? null)) {
            $query->where('interested_course_id', (int) $filters['course_id']);
        }

        if (filled($filters['subject_id'] ?? null)) {
            $subjectId = (int) $filters['subject_id'];
            $query->whereHas('interestedCourse', static fn (EloquentBuilder $q): EloquentBuilder => $q->where('subject_id', $subjectId));
        }

        if (filled($filters['topic_id'] ?? null)) {
            $topicId = (int) $filters['topic_id'];
            $query->whereHas('interestedCourse', static fn (EloquentBuilder $q): EloquentBuilder => $q->where('course_topic_id', $topicId));
        }
    }

    /**
     * Build active filter pill list.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{key: string, label: string}>
     */
    private function buildActiveFilterTags(array $filters): array
    {
        $tags = [];

        if (filled($filters['search'] ?? null)) {
            $tags[] = ['key' => 'search', 'label' => 'Search: '.trim($filters['search'])];
        }

        if (! empty($filters['overdue'])) {
            $tags[] = ['key' => 'overdue', 'label' => 'Overdue follow-ups'];
        }

        if (filled($filters['status'] ?? null)) {
            $tags[] = ['key' => 'status', 'label' => 'Status: '.$filters['status']];
        }

        if (filled($filters['team_id'] ?? null)) {
            $team = Team::query()->find($filters['team_id']);
            if ($team) {
                $tags[] = ['key' => 'team_id', 'label' => 'Team: '.$team->name];
            }
        }

        if (filled($filters['owner'] ?? null)) {
            if ($filters['owner'] === 'unassigned') {
                $tags[] = ['key' => 'owner', 'label' => 'Owner: Unassigned'];
            } else {
                $emp = Employee::query()->find($filters['owner']);
                if ($emp) {
                    $tags[] = ['key' => 'owner', 'label' => "Owner: ({$emp->office_id} - {$emp->name})"];
                }
            }
        }

        if (filled($filters['created'] ?? null)) {
            $label = match ($filters['created']) {
                'today' => 'Today',
                '7days' => 'Last 7 days',
                '30days' => 'This month',
                default => $filters['created'],
            };
            $tags[] = ['key' => 'created', 'label' => 'Created: '.$label];
        }

        if (filled($filters['channel'] ?? null)) {
            $channelLabel = LeadSource::CHANNELS[$filters['channel']] ?? ucfirst($filters['channel']);
            $tags[] = ['key' => 'channel', 'label' => 'Channel: '.$channelLabel];
        }

        if (filled($filters['campaign'] ?? null)) {
            $tags[] = ['key' => 'campaign', 'label' => 'Campaign: '.$filters['campaign']];
        }

        if (filled($filters['course_id'] ?? null)) {
            $course = Course::query()->find($filters['course_id']);
            if ($course) {
                $tags[] = ['key' => 'course_id', 'label' => 'Course: '.$course->title];
            }
        }

        if (filled($filters['subject_id'] ?? null)) {
            $subject = Subject::query()->find($filters['subject_id']);
            if ($subject) {
                $tags[] = ['key' => 'subject_id', 'label' => 'Subject: '.$subject->name];
            }
        }

        if (filled($filters['topic_id'] ?? null)) {
            $topic = CourseTopic::query()->find($filters['topic_id']);
            if ($topic) {
                $tags[] = ['key' => 'topic_id', 'label' => 'Topic: '.$topic->name];
            }
        }

        return $tags;
    }

    private function authenticatedEmployee(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless(
            $employee instanceof Employee
                && in_array($employee->role, ['Admin', 'MarketingExecutive', 'Supervisor', 'Team Leader', 'Sales Executive'], true),
            403,
            'You are not authorized to access leads.',
        );

        return $employee;
    }

    private function authorizeAssignment(Request $request): Employee
    {
        $employee = $this->authenticatedEmployee($request);

        abort_unless(
            $employee->isAdmin() || $employee->role === 'MarketingExecutive',
            403,
            'Only administrators and marketers are authorized to assign leads.',
        );

        return $employee;
    }
}
