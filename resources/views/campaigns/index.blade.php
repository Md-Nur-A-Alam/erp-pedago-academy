@extends('layouts.app', ['title' => 'Campaigns'])

@section('page-title', 'Campaigns')
@section('page-description', 'Review campaign performance and manage course-linked campaigns.')
@section('page-actions')
    <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="button" data-open-dialog="campaign-create-modal">
        + Create campaign
    </button>
@endsection

@section('content')
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Campaign summary">
        @foreach ([
            ['label' => 'Total campaigns', 'value' => $stats['total']],
            ['label' => 'Active', 'value' => $stats['active']],
            ['label' => 'Auto distribution', 'value' => $stats['auto']],
            ['label' => 'Manual assignment', 'value' => $stats['manual']],
            ['label' => 'Not linked to a subject', 'value' => $stats['unlinked']],
        ] as $stat)
            <article @class([
                'rounded-lg border bg-white px-4 py-3',
                'border-rose-200' => $stat['label'] === 'Not linked to a subject' && $stat['value'] > 0,
                'border-pedago-line' => $stat['label'] !== 'Not linked to a subject' || $stat['value'] === 0,
            ])>
                <p class="text-xs text-pedago-muted">{{ $stat['label'] }}</p>
                <p @class([
                    'mt-1 text-2xl font-bold leading-none',
                    'text-rose-700' => $stat['label'] === 'Not linked to a subject' && $stat['value'] > 0,
                    'text-pedago-ink' => $stat['label'] !== 'Not linked to a subject' || $stat['value'] === 0,
                ])>{{ number_format($stat['value']) }}</p>
                @if ($stat['label'] === 'Not linked to a subject' && $stat['value'] > 0)
                    <p class="mt-1 text-[11px] text-pedago-muted">Campaigns without course subject details</p>
                @endif
            </article>
        @endforeach
    </section>

    <section class="flex flex-col gap-3 rounded-lg border border-pedago-line bg-white p-4" aria-labelledby="campaign-filter-heading">
        <h2 class="text-sm font-bold" id="campaign-filter-heading">Filter campaigns</h2>
        <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" method="GET" action="{{ route('campaigns.index') }}">
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="search">
                Search
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Campaign name or ID">
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="filter-channel">
                Channel
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="filter-channel" name="channel">
                    <option value="">All channels</option>
                    @foreach ($channels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['channel'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="filter-subject">
                Subject
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="filter-subject" name="subject">
                    <option value="">All subjects</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((string) $filters['subject'] === (string) $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="filter-topic">
                Topic
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="filter-topic" name="topic">
                    <option value="">All topics</option>
                    @foreach ($topics as $topic)
                        <option value="{{ $topic->id }}" data-subject="{{ $topic->subject_id }}" @selected((string) $filters['topic'] === (string) $topic->id)>{{ $topic->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="filter-mode">
                Distribution mode
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="filter-mode" name="mode">
                    <option value="">All modes</option>
                    @foreach ($distributionModes as $mode)
                        <option value="{{ $mode }}" @selected($filters['mode'] === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="filter-status">
                Status
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="filter-status" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="filter-created">
                Created
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="filter-created" name="created">
                    <option value="">Any time</option>
                    <option value="today" @selected($filters['created'] === 'today')>Today</option>
                    <option value="7days" @selected($filters['created'] === '7days')>Last 7 days</option>
                    <option value="30days" @selected($filters['created'] === '30days')>Last 30 days</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="sort">
                Sort
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="sort" name="sort">
                    <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                    <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
                    <option value="leads_desc" @selected($filters['sort'] === 'leads_desc')>Most leads first</option>
                    <option value="leads_asc" @selected($filters['sort'] === 'leads_asc')>Fewest leads first</option>
                </select>
            </label>

            <div class="flex items-end gap-2 lg:col-span-4">
                <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">
                    Apply filters
                </button>
                <a class="inline-flex items-center justify-center rounded-md border border-pedago-blue px-4 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('campaigns.index') }}">
                    Clear filters
                </a>
            </div>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                @if ($activeFilters !== [])
                    <span class="text-pedago-muted">Active filters:</span>
                    @foreach ($activeFilters as $activeFilter)
                        <a class="inline-flex items-center gap-2 rounded-full bg-pedago-tint px-3 py-1 font-semibold text-pedago-blue-dark hover:bg-pedago-blue/15" href="{{ route('campaigns.index', request()->except($activeFilter['key'], 'page')) }}">
                            {{ $activeFilter['label'] }} <span aria-hidden="true">×</span>
                            <span class="sr-only">Remove filter</span>
                        </a>
                    @endforeach
                @else
                    <span class="text-pedago-muted">No active filters</span>
                @endif
            </div>
            <span class="text-pedago-muted">Sort: <strong class="text-pedago-ink">{{ match ($filters['sort']) {
                'oldest' => 'Oldest first',
                'leads_desc' => 'Most leads first',
                'leads_asc' => 'Fewest leads first',
                default => 'Newest first',
            } }}</strong></span>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-pedago-line bg-white" aria-labelledby="campaign-list-heading">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-pedago-line px-4 py-3">
            <h2 class="text-sm font-bold" id="campaign-list-heading">Campaigns</h2>
            <span class="text-xs text-pedago-muted">Showing {{ $campaigns->firstItem() ?? 0 }}–{{ $campaigns->lastItem() ?? 0 }} of {{ number_format($campaigns->total()) }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] border-collapse text-left">
                <thead class="border-b border-pedago-line text-[10px] uppercase tracking-wide text-pedago-muted">
                    <tr>
                        <th class="w-10 px-3 py-3">
                            <input class="size-3.5 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" aria-label="Select all campaigns on this page" data-select-all>
                        </th>
                        <th class="px-3 py-3 font-semibold">ID</th>
                        <th class="px-3 py-3 font-semibold">Campaign</th>
                        <th class="px-3 py-3 font-semibold">Subject &gt; Topic</th>
                        <th class="px-3 py-3 font-semibold">Channel</th>
                        <th class="px-3 py-3 font-semibold">Mode</th>
                        <th class="px-3 py-3 font-semibold">Leads</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-3 py-3 font-semibold">Created</th>
                        <th class="px-3 py-3 font-semibold">Last edited</th>
                        <th class="px-3 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pedago-line text-xs">
                    @forelse ($campaigns as $campaign)
                        <tr class="transition-colors hover:bg-pedago-canvas/60">
                            <td class="px-3 py-3">
                                <input class="size-3.5 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" value="{{ $campaign->source_id }}" aria-label="Select {{ $campaign->campaign_name }}" data-campaign-checkbox>
                            </td>
                            <td class="px-3 py-3 font-semibold">{{ $campaign->source_id }}</td>
                            <td class="max-w-56 px-3 py-3">
                                <span class="block truncate font-semibold text-pedago-ink" title="{{ $campaign->campaign_name }}">{{ $campaign->campaign_name }}</span>
                            </td>
                            <td class="px-3 py-3">
                                @if ($campaign->course?->subject)
                                    <span class="inline-flex max-w-56 items-center gap-1 rounded-full bg-pedago-tint px-2 py-1 text-[10px] font-semibold text-pedago-blue-dark">
                                        <span aria-hidden="true">●</span>
                                        <span class="truncate">{{ $campaign->course->subject->name }}@if ($campaign->course->topic) &gt; {{ $campaign->course->topic->name }}@endif</span>
                                    </span>
                                    <span class="mt-1 block max-w-56 truncate text-[10px] text-pedago-muted" title="Course: {{ $campaign->course->title }}">Course: {{ $campaign->course->title }}</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800">Not linked</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-pedago-ink">{{ $channels[$campaign->channel] ?? $campaign->channel }}</td>
                            <td class="px-3 py-3">
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-semibold',
                                    'bg-pedago-tint text-pedago-blue-dark' => $campaign->distribution_mode === 'Auto',
                                    'bg-amber-100 text-amber-800' => $campaign->distribution_mode === 'Manual',
                                ])><span aria-hidden="true">●</span> {{ $campaign->distribution_mode }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">{{ number_format($campaign->leads_count) }}</td>
                            <td class="px-3 py-3">
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold',
                                    'border-pedago-line text-pedago-green' => $campaign->is_active,
                                    'border-pedago-line text-pedago-muted' => ! $campaign->is_active,
                                ])><span aria-hidden="true">●</span> {{ $campaign->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                {{ $campaign->created_at?->format('d M Y') ?? '—' }}
                                <span class="mt-0.5 block text-[10px] text-pedago-muted">by {{ $campaign->createdBy?->name ?? 'Unknown' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                {{ $campaign->edited_at?->format('d M Y') ?? '—' }}
                                @if ($campaign->editedBy)
                                    <span class="mt-0.5 block text-[10px] text-pedago-muted">{{ $campaign->editedBy->name }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5">
                                    <button class="rounded-md border border-pedago-blue px-2.5 py-1.5 text-[10px] font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" type="button"
                                        data-edit-campaign
                                        data-id="{{ $campaign->source_id }}"
                                        data-name="{{ $campaign->campaign_name }}"
                                        data-channel="{{ $campaign->channel }}"
                                        data-course-id="{{ $campaign->course_id }}"
                                        data-mode="{{ $campaign->distribution_mode }}"
                                        data-active="{{ $campaign->is_active ? '1' : '0' }}"
                                        data-leads="{{ $campaign->leads_count }}"
                                        data-created="{{ $campaign->created_at?->format('d M Y') ?? '—' }}"
                                        data-creator="{{ $campaign->createdBy?->name ?? 'Unknown' }}"
                                        data-edited="{{ $campaign->edited_at?->format('d M Y') ?? '—' }}"
                                        data-editor="{{ $campaign->editedBy?->name ?? '—' }}">
                                        Edit
                                    </button>
                                    <button class="rounded-md border border-rose-200 px-2.5 py-1.5 text-[10px] font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40" type="button" data-delete-campaign data-id="{{ $campaign->source_id }}" data-name="{{ $campaign->campaign_name }}" data-leads="{{ $campaign->leads_count }}" @disabled($campaign->leads_count > 0) title="{{ $campaign->leads_count > 0 ? 'Campaigns with leads cannot be deleted; deactivate instead' : 'Delete campaign' }}">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-10 text-center text-sm text-pedago-muted" colspan="11">No campaigns match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-pedago-line px-4 py-3 text-xs text-pedago-muted">
            <span>Rows per page: 25 · Page {{ $campaigns->currentPage() }} of {{ $campaigns->lastPage() }}</span>
            @if ($campaigns->hasPages())
                {{ $campaigns->links() }}
            @endif
        </div>
    </section>

    <p class="text-xs text-pedago-muted">Campaigns with leads cannot be deleted. Deactivate them instead.</p>

    <dialog id="campaign-create-modal" class="m-auto max-h-[92vh] w-[min(94vw,32rem)] overflow-y-auto rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-sm font-bold">Create campaign</h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <form class="flex flex-col gap-4 p-5" method="POST" action="{{ route('campaigns.store') }}" data-campaign-form>
            @csrf
            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="create-channel">Channel <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-channel" name="channel" required>
                    <option value="">Select a channel</option>
                    @foreach ($channels as $value => $label)
                        <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('channel')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="create-course">Course <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-course" name="course_id" required data-course-select>
                    <option value="">Select a course</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" data-subject="{{ $course->subject?->name }}" data-topic="{{ $course->topic?->name }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->title }} ({{ $course->code }})</option>
                    @endforeach
                </select>
                @error('course_id')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
                <span class="text-[11px] font-normal text-pedago-muted">The selected course’s subject and topic are shown with this campaign.</span>
            </label>

            <div class="flex min-h-11 flex-wrap items-center gap-2 rounded-md bg-pedago-canvas px-3 py-2 text-xs" data-course-preview>
                <span class="text-pedago-muted">Course details</span>
                <span class="text-pedago-muted" data-preview-empty>Choose a course to preview its subject and topic.</span>
                <span class="hidden rounded-full bg-pedago-tint px-2 py-1 font-semibold text-pedago-blue-dark" data-preview-subject></span>
                <span class="hidden rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-800" data-preview-topic></span>
                @if (auth()->user()->employee?->isAdmin())
                    <a class="ml-auto text-[11px] font-semibold text-pedago-blue-dark underline" href="{{ route('subjects.index') }}">Manage subjects and topics</a>
                @endif
            </div>

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="create-campaign-name">Campaign name <span class="text-pedago-red">*</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-campaign-name" maxlength="150" name="campaign_name" type="text" value="{{ old('campaign_name') }}" placeholder="e.g. Admission Nov · Reels" required data-name-input>
                @error('campaign_name')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>
            <div class="-mt-2 flex justify-between text-[11px] text-pedago-muted">
                <span>Shown in lead upload and reports.</span>
                <span><span data-name-count>0</span> / 150</span>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-xs font-semibold">Distribution mode</legend>
                @foreach ($distributionModes as $mode)
                    <label class="flex cursor-pointer items-start gap-2 rounded-md border border-pedago-line px-3 py-2.5 has-[:checked]:border-pedago-blue has-[:checked]:bg-pedago-tint">
                        <input class="mt-0.5 size-4 accent-pedago-blue" type="radio" name="distribution_mode" value="{{ $mode }}" @checked(old('distribution_mode', 'Auto') === $mode) required>
                        <span class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold">{{ $mode }}</span>
                            <span class="text-[11px] text-pedago-muted">{{ $mode === 'Auto' ? 'New leads go straight to the distribution engine.' : 'New leads wait in the central queue for manual assignment.' }}</span>
                        </span>
                    </label>
                @endforeach
                @error('distribution_mode')<span class="text-xs font-normal text-pedago-red">{{ $message }}</span>@enderror
            </fieldset>

            <div class="flex items-center justify-between border-t border-pedago-line pt-3">
                <span class="text-xs font-semibold">Campaign status</span>
                <span class="inline-flex items-center gap-1 rounded-md border border-pedago-line px-2.5 py-1.5 text-xs font-semibold text-pedago-green"><span aria-hidden="true">●</span> Active</span>
            </div>
            <div class="flex justify-end gap-2 border-t border-pedago-line pt-4">
                <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
                <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white hover:bg-pedago-blue-dark" type="submit">Create campaign</button>
            </div>
        </form>
    </dialog>

    <dialog id="campaign-edit-modal" class="m-auto max-h-[92vh] w-[min(94vw,32rem)] overflow-y-auto rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-sm font-bold">Edit campaign</h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <form class="flex flex-col gap-4 p-5" id="campaign-edit-form" method="POST" action="" data-campaign-form>
            @csrf
            @method('PUT')
            <input id="edit-campaign-id" name="campaign_id" type="hidden" value="{{ old('campaign_id') }}">
            <p class="rounded-md bg-pedago-canvas px-3 py-2 text-[11px] text-pedago-muted">
                ID <span data-edit-id></span> · Created <span data-edit-created></span> by <span data-edit-creator></span> · Last edited <span data-edit-last-edited></span> by <span data-edit-editor></span>
            </p>

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-channel">Channel <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-channel" name="channel" required>
                    <option value="">Select a channel</option>
                    @foreach ($channels as $value => $label)
                        <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('channel')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-course">Course <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-course" name="course_id" required data-course-select>
                    <option value="">Select a course</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" data-subject="{{ $course->subject?->name }}" data-topic="{{ $course->topic?->name }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->title }} ({{ $course->code }})</option>
                    @endforeach
                </select>
                @error('course_id')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <div class="flex min-h-11 flex-wrap items-center gap-2 rounded-md bg-pedago-canvas px-3 py-2 text-xs" data-course-preview>
                <span class="text-pedago-muted">Leads will show</span>
                <span class="text-pedago-muted" data-preview-empty>Choose a course to preview its subject and topic.</span>
                <span class="hidden rounded-full bg-pedago-tint px-2 py-1 font-semibold text-pedago-blue-dark" data-preview-subject></span>
                <span class="hidden rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-800" data-preview-topic></span>
                @if (auth()->user()->employee?->isAdmin())
                    <a class="ml-auto text-[11px] font-semibold text-pedago-blue-dark underline" href="{{ route('subjects.index') }}">Manage subjects and topics</a>
                @endif
            </div>

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-campaign-name">Campaign name <span class="text-pedago-red">*</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-campaign-name" maxlength="150" name="campaign_name" type="text" value="{{ old('campaign_name') }}" required data-name-input>
                @error('campaign_name')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>
            <div class="-mt-2 flex justify-between text-[11px] text-pedago-muted">
                <span>Shown in lead upload and reports.</span>
                <span><span data-name-count>0</span> / 150</span>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-xs font-semibold">Distribution mode</legend>
                @foreach ($distributionModes as $mode)
                    <label class="flex cursor-pointer items-start gap-2 rounded-md border border-pedago-line px-3 py-2.5 has-[:checked]:border-pedago-blue has-[:checked]:bg-pedago-tint">
                        <input class="mt-0.5 size-4 accent-pedago-blue" type="radio" name="distribution_mode" value="{{ $mode }}" @checked(old('distribution_mode') === $mode) required>
                        <span class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold">{{ $mode }}</span>
                            <span class="text-[11px] text-pedago-muted">{{ $mode === 'Auto' ? 'New leads go straight to the distribution engine.' : 'New leads wait in the central queue for manual assignment.' }}</span>
                        </span>
                    </label>
                @endforeach
                @error('distribution_mode')<span class="text-xs font-normal text-pedago-red">{{ $message }}</span>@enderror
            </fieldset>

            <div class="flex items-center justify-between border-t border-pedago-line pt-3">
                <span class="text-xs font-semibold">Campaign is active</span>
                <input name="is_active" type="hidden" value="0">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-pedago-line px-2.5 py-1.5 text-xs font-semibold text-pedago-green">
                    <input class="size-4 accent-pedago-blue" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1') data-active-checkbox>
                    <span data-active-label>Active</span>
                </label>
            </div>
            @error('is_active')<p class="text-xs text-pedago-red">{{ $message }}</p>@enderror

            <div class="flex justify-between gap-2 border-t border-pedago-line pt-4">
                <div>
                    <button class="rounded-md border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40" type="button" data-edit-delete>Delete</button>
                    <p class="mt-1 text-[10px] text-pedago-muted" data-edit-delete-note></p>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
                    <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white hover:bg-pedago-blue-dark" type="submit">Save changes</button>
                </div>
            </div>
        </form>
    </dialog>

    <dialog id="campaign-delete-modal" class="m-auto w-[min(94vw,28rem)] rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-sm font-bold">Delete campaign?</h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <div class="flex flex-col gap-3 px-5 py-4 text-xs leading-5 text-pedago-muted">
            <p>You are deleting <strong class="text-pedago-ink" data-delete-name></strong> (ID <span data-delete-id></span>). It has no leads attached.</p>
            <p>This cannot be undone.</p>
        </div>
        <form class="flex justify-end gap-2 border-t border-pedago-line px-5 py-3" id="campaign-delete-form" method="POST" action="">
            @csrf
            @method('DELETE')
            <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
            <button class="rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800" type="submit">Delete campaign</button>
        </form>
    </dialog>
@endsection

@pushOnce('scripts')
    <script>
        const campaignDialogs = {
            create: document.getElementById('campaign-create-modal'),
            edit: document.getElementById('campaign-edit-modal'),
            delete: document.getElementById('campaign-delete-modal'),
        };
        const editForm = document.getElementById('campaign-edit-form');
        const deleteForm = document.getElementById('campaign-delete-form');

        document.querySelectorAll('[data-open-dialog]').forEach((button) => {
            button.addEventListener('click', () => campaignDialogs.create.showModal());
        });

        document.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog').close());
        });

        document.querySelectorAll('dialog').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });

        document.querySelectorAll('[data-course-select]').forEach((select) => {
            const preview = select.closest('form').querySelector('[data-course-preview]');
            const subject = preview.querySelector('[data-preview-subject]');
            const topic = preview.querySelector('[data-preview-topic]');
            const empty = preview.querySelector('[data-preview-empty]');

            const updatePreview = () => {
                const option = select.selectedOptions[0];
                const subjectName = option?.dataset.subject || '';
                const topicName = option?.dataset.topic || '';

                subject.textContent = subjectName;
                topic.textContent = topicName;
                subject.classList.toggle('hidden', !subjectName);
                topic.classList.toggle('hidden', !topicName);
                empty.classList.toggle('hidden', Boolean(subjectName || topicName));
            };

            select.addEventListener('change', updatePreview);
            updatePreview();
        });

        const subjectFilter = document.getElementById('filter-subject');
        const topicFilter = document.getElementById('filter-topic');
        subjectFilter.addEventListener('change', () => {
            Array.from(topicFilter.options).slice(1).forEach((option) => {
                option.hidden = Boolean(subjectFilter.value) && option.dataset.subject !== subjectFilter.value;
            });

            if (topicFilter.selectedOptions[0]?.hidden) {
                topicFilter.value = '';
            }
        });
        subjectFilter.dispatchEvent(new Event('change'));

        document.querySelectorAll('[data-name-input]').forEach((input) => {
            const count = input.closest('form').querySelector('[data-name-count]');
            const updateCount = () => count.textContent = input.value.length;
            input.addEventListener('input', updateCount);
            updateCount();
        });

        document.querySelectorAll('[data-active-checkbox]').forEach((checkbox) => {
            const label = checkbox.closest('label').querySelector('[data-active-label]');
            checkbox.addEventListener('change', () => {
                label.textContent = checkbox.checked ? 'Active' : 'Inactive';
                label.classList.toggle('text-pedago-green', checkbox.checked);
                label.classList.toggle('text-pedago-muted', !checkbox.checked);
            });
        });

        const openEditDialog = (button, restoreOldInput = false) => {
            const data = button.dataset;

            editForm.action = `{{ url('/campaigns') }}/${data.id}`;
            document.getElementById('edit-campaign-id').value = data.id;
            document.querySelector('[data-edit-id]').textContent = data.id;
            document.querySelector('[data-edit-created]').textContent = data.created;
            document.querySelector('[data-edit-creator]').textContent = data.creator;
            document.querySelector('[data-edit-last-edited]').textContent = data.edited;
            document.querySelector('[data-edit-editor]').textContent = data.editor;
            document.querySelector('[data-edit-delete-note]').textContent = data.leads > 0 ? `Used by ${data.leads} leads. Deactivate instead.` : '';
            const editDeleteButton = document.querySelector('[data-edit-delete]');
            editDeleteButton.disabled = Number(data.leads) > 0;
            editDeleteButton.title = data.leads > 0 ? 'Campaigns with leads cannot be deleted' : 'Delete campaign';

            if (!restoreOldInput) {
                document.getElementById('edit-channel').value = data.channel;
                document.getElementById('edit-course').value = data.courseId || '';
                document.getElementById('edit-campaign-name').value = data.name;
                editForm.querySelectorAll('input[name="distribution_mode"][type="radio"]').forEach((radio) => {
                    radio.checked = radio.value === data.mode;
                });
                const active = editForm.querySelector('[data-active-checkbox]');
                active.checked = data.active === '1';
                active.dispatchEvent(new Event('change'));
                editForm.querySelector('[data-course-select]').dispatchEvent(new Event('change'));
                editForm.querySelector('[data-name-input]').dispatchEvent(new Event('input'));
            }

            campaignDialogs.edit.showModal();
        };

        document.querySelectorAll('[data-edit-campaign]').forEach((button) => {
            button.addEventListener('click', () => openEditDialog(button));
        });

        document.querySelectorAll('[data-delete-campaign]').forEach((button) => {
            button.addEventListener('click', () => {
                const data = button.dataset;
                deleteForm.action = `{{ url('/campaigns') }}/${data.id}`;
                document.querySelector('[data-delete-name]').textContent = data.name;
                document.querySelector('[data-delete-id]').textContent = data.id;
                campaignDialogs.delete.showModal();
            });
        });

        document.querySelector('[data-edit-delete]').addEventListener('click', () => {
            const campaignId = document.getElementById('edit-campaign-id').value;
            const campaignButton = Array.from(document.querySelectorAll('[data-edit-campaign]'))
                .find((button) => button.dataset.id === campaignId);

            if (campaignButton?.dataset.leads === '0') {
                campaignDialogs.edit.close();
                campaignButton.parentElement.querySelector('[data-delete-campaign]').click();
            }
        });

        document.querySelector('[data-select-all]').addEventListener('change', (event) => {
            document.querySelectorAll('[data-campaign-checkbox]').forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
        });

        if (new URLSearchParams(window.location.search).get('create') === '1') {
            campaignDialogs.create.showModal();
        }

        const oldCampaignId = @js(old('campaign_id'));
        if (oldCampaignId) {
            const oldCampaignButton = Array.from(document.querySelectorAll('[data-edit-campaign]'))
                .find((button) => button.dataset.id === String(oldCampaignId));

            if (oldCampaignButton) {
                openEditDialog(oldCampaignButton, true);
                editForm.querySelector('[data-active-checkbox]').dispatchEvent(new Event('change'));
            }
        } else if (@js($errors->any() && ! $errors->has('campaign'))) {
            campaignDialogs.create.showModal();
        }
    </script>
@endPushOnce
