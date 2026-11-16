@extends('layouts.app', ['title' => 'All leads'])

@section('page-title', 'All leads')
@section('page-description', 'Manage student leads, real-time allocations, campaigns, and conversion pipeline.')
@section('page-actions')
    <div class="flex flex-wrap items-center gap-2">
        <a class="inline-flex items-center justify-center rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-semibold text-pedago-ink shadow-xs transition hover:bg-pedago-canvas focus-visible:outline-2 focus-visible:outline-pedago-blue" href="{{ route('leads.export', request()->query()) }}">
            <svg class="mr-1.5 size-4 text-pedago-muted" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Export CSV
        </a>
        @if ($canAssign || in_array($currentEmployee->role, ['Supervisor', 'Team Leader'], true))
            <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="button" data-open-dialog="lead-create-modal">
                + Add lead
            </button>
        @endif
    </div>
@endsection

@section('content')
    {{-- 1. KPI Summary Cards --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Lead statistics">
        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3 shadow-xs">
            <p class="text-xs font-medium text-pedago-muted">Total leads</p>
            <p class="mt-1 font-mono text-2xl font-bold leading-none text-pedago-ink">{{ number_format($stats['total_leads']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">Lifetime registered</p>
        </article>

        <article @class([
            'rounded-lg border bg-white px-4 py-3 shadow-xs',
            'border-amber-300 bg-amber-50/20' => $stats['unassigned'] > 0,
            'border-pedago-line' => $stats['unassigned'] === 0,
        ])>
            <p class="text-xs font-medium text-pedago-muted">Unassigned</p>
            <p class="mt-1 font-mono text-2xl font-bold leading-none text-amber-600">{{ number_format($stats['unassigned']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">Awaiting allocation</p>
        </article>

        {{-- 3. Overdue Follow-up (Red Danger Block with Stop Hand Icon if > 0, Normal if 0) --}}
        @php
            $hasOverdue = $stats['overdue_followups'] > 0;
            $isOverdueFilterActive = (bool) request('overdue');
        @endphp
        <a href="{{ request()->fullUrlWithQuery(['overdue' => $isOverdueFilterActive ? null : 1]) }}" @class([
            'rounded-lg px-4 py-3 shadow-xs block transition relative',
            // Red Danger State when > 0
            'bg-red-600 hover:bg-red-700 text-white border border-red-700 shadow-md' => $hasOverdue && ! $isOverdueFilterActive,
            'bg-red-700 text-white border-2 border-white ring-2 ring-red-500 shadow-lg' => $hasOverdue && $isOverdueFilterActive,
            // Normal State when 0
            'border border-pedago-line bg-white text-pedago-ink hover:border-pedago-muted/60' => ! $hasOverdue && ! $isOverdueFilterActive,
            'border-2 border-pedago-blue bg-pedago-tint/50 text-pedago-ink' => ! $hasOverdue && $isOverdueFilterActive,
        ]) title="Click to filter by overdue follow-up date">
            <div class="flex items-center justify-between">
                <span @class([
                    'text-xs font-semibold uppercase tracking-wider',
                    'text-white' => $hasOverdue,
                    'text-pedago-muted' => ! $hasOverdue,
                ])>
                    Overdue follow-up
                </span>

                {{-- Stop Hand Icon --}}
                <div @class([
                    'flex size-7 items-center justify-center rounded-md transition-transform group-hover:scale-105',
                    'bg-white/20 text-white shadow-xs' => $hasOverdue,
                    'bg-pedago-canvas text-pedago-muted' => ! $hasOverdue,
                ])>
                    <svg class="size-4.5" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">
                        <path d="M288 32c0-17.7-14.3-32-32-32s-32 14.3-32 32V240c0 8.8-7.2 16-16 16s-16-7.2-16-16V64c0-17.7-14.3-32-32-32s-32 14.3-32 32V240c0 8.8-7.2 16-16 16s-16-7.2-16-16V128c0-17.7-14.3-32-32-32s-32 14.3-32 32v176c0 114.9 93.1 208 208 208h32c114.9 0 208-93.1 208-208V160c0-17.7-14.3-32-32-32s-32 14.3-32 32v80c0 8.8-7.2 16-16 16s-16-7.2-16-16V32z"/>
                    </svg>
                </div>
            </div>

            <p @class([
                'mt-1.5 font-mono text-2xl font-bold leading-none',
                'text-white' => $hasOverdue,
                'text-pedago-ink' => ! $hasOverdue,
            ])>
                {{ number_format($stats['overdue_followups']) }}
            </p>

            <p @class([
                'mt-1 text-[11px]',
                'text-white/90 font-medium' => $hasOverdue,
                'text-pedago-muted' => ! $hasOverdue,
            ])>
                {{ $hasOverdue ? 'Past scheduled date • Action required' : 'None past scheduled date' }}
            </p>
        </a>

        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3 shadow-xs">
            <p class="text-xs font-medium text-pedago-muted">Follow-up</p>
            <p class="mt-1 font-mono text-2xl font-bold leading-none text-blue-700">{{ number_format($stats['follow_up']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">Active conversations</p>
        </article>

        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3 shadow-xs">
            <p class="text-xs font-medium text-pedago-muted">Converted</p>
            <p class="mt-1 font-mono text-2xl font-bold leading-none text-emerald-700">{{ number_format($stats['converted']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">Successful admissions</p>
        </article>
    </section>

    {{-- 2. Filter Leads Section --}}
    <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-xs">
        <div class="mb-4 flex items-center justify-between border-b border-pedago-line pb-3">
            <h2 class="text-sm font-bold text-pedago-ink">Filter leads</h2>
            <a class="text-xs font-medium text-pedago-blue hover:underline" href="{{ route('leads.index') }}">Reset filters</a>
        </div>

        <form method="GET" action="{{ route('leads.index') }}">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                {{-- Search --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="q">Search</label>
                    <input class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-3 text-xs text-pedago-ink placeholder:text-pedago-muted/60 focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="q" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Phone, name or lead code">
                </div>

                {{-- Status --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fs">Status</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fs" name="status">
                        <option value="">All statuses</option>
                        @foreach ([
                            'New' => 'New',
                            'Assigned' => 'Assigned',
                            'Follow-up' => 'Follow-up',
                            'overdue' => 'Overdue follow-up',
                            'Interested' => 'Interested',
                            'Pending payment' => 'Pending payment',
                            'Converted' => 'Converted',
                            'Not interested' => 'Not interested',
                            'Lost' => 'Lost',
                        ] as $val => $lbl)
                            <option value="{{ $val }}" @selected(($filters['status'] ?? null) === $val || ($val === 'overdue' && request('overdue')))>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Team --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="ft">Team</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="ft" name="team_id">
                        <option value="">All teams</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected((int) ($filters['team_id'] ?? 0) === $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Owner --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fo">Owner</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fo" name="owner">
                        <option value="">All owners</option>
                        <option value="unassigned" @selected(($filters['owner'] ?? null) === 'unassigned')>Unassigned</option>
                        @foreach ($salesPersons as $sp)
                            <option value="{{ $sp->id }}" @selected((string) ($filters['owner'] ?? '') === (string) $sp->id)>({{ $sp->office_id }} - {{ $sp->name }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Created --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fd">Created</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fd" name="created">
                        <option value="">Any time</option>
                        <option value="today" @selected(($filters['created'] ?? null) === 'today')>Today</option>
                        <option value="7days" @selected(($filters['created'] ?? null) === '7days')>Last 7 days</option>
                        <option value="30days" @selected(($filters['created'] ?? null) === '30days')>This month</option>
                    </select>
                </div>

                {{-- Channel --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fc">Channel</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fc" name="channel">
                        <option value="">All channels</option>
                        @foreach ($channels as $code => $chName)
                            <option value="{{ $code }}" @selected(($filters['channel'] ?? null) === $code)>{{ $chName }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Campaign --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fp">Campaign</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fp" name="campaign">
                        <option value="">All campaigns</option>
                        @foreach ($campaigns as $camp)
                            <option value="{{ $camp }}" @selected(($filters['campaign'] ?? null) === $camp)>{{ $camp }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Course --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fcourse">Course</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fcourse" name="course_id">
                        <option value="">All courses</option>
                        @foreach ($courses as $c)
                            <option value="{{ $c->id }}" @selected((int) ($filters['course_id'] ?? 0) === $c->id)>{{ $c->title }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Subject --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fj">Subject</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fj" name="subject_id">
                        <option value="">All subjects</option>
                        @foreach ($subjects as $s)
                            <option value="{{ $s->id }}" @selected((int) ($filters['subject_id'] ?? 0) === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Topic --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-pedago-muted" for="fx">Topic</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:bg-white focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="fx" name="topic_id">
                        <option value="">All topics</option>
                        @foreach ($topics as $tp)
                            <option value="{{ $tp->id }}" @selected((int) ($filters['topic_id'] ?? 0) === $tp->id)>{{ $tp->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <input type="hidden" name="sort" value="{{ $sort }}">

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-pedago-line pt-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button class="inline-flex h-9 items-center justify-center rounded-md bg-pedago-blue px-4 text-xs font-semibold text-white shadow-xs transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-pedago-blue" type="submit">
                        Apply filters
                    </button>
                    <a class="inline-flex h-9 items-center justify-center rounded-md border border-pedago-line bg-white px-3 text-xs font-semibold text-pedago-ink transition hover:bg-pedago-canvas focus-visible:outline-2 focus-visible:outline-pedago-blue" href="{{ route('leads.index') }}">
                        Clear filters
                    </a>
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <span class="font-semibold text-pedago-muted">Sort:</span>
                    <select class="h-8 rounded-md border border-pedago-line bg-white px-2 text-xs font-medium text-pedago-ink focus:border-pedago-blue focus:outline-none" onchange="window.location.href = this.value">
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" @selected($sort === 'newest')>Newest first</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}" @selected($sort === 'oldest')>Oldest first</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}" @selected($sort === 'name_asc')>Name (A-Z)</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}" @selected($sort === 'name_desc')>Name (Z-A)</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'status']) }}" @selected($sort === 'status')>Status</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'last_contact']) }}" @selected($sort === 'last_contact')>Last contact</option>
                    </select>
                </div>
            </div>
        </form>

        {{-- Active Filters Pills --}}
        @if ($activeFilters !== [])
            <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-dashed border-pedago-line pt-3 text-xs">
                <span class="font-semibold text-pedago-muted">Active filters:</span>
                @foreach ($activeFilters as $tag)
                    <span class="inline-flex items-center gap-1 rounded-md bg-pedago-tint px-2 py-0.5 text-xs font-medium text-pedago-blue-dark">
                        {{ $tag['label'] }}
                        <a class="ml-1 text-pedago-blue-dark/70 hover:text-pedago-blue-dark" href="{{ request()->fullUrlWithQuery([$tag['key'] => null]) }}" aria-label="Remove filter {{ $tag['label'] }}">×</a>
                    </span>
                @endforeach
            </div>
        @endif
    </section>

    {{-- 3. Leads Table Section --}}
    <section class="rounded-xl border border-pedago-line bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-pedago-line px-5 py-4">
            <div>
                <h2 class="text-sm font-bold text-pedago-ink">All leads</h2>
                <p class="text-xs text-pedago-muted">
                    Showing {{ $leads->firstItem() ?? 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ number_format($leads->total()) }} leads
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] border-collapse text-left">
                <thead class="border-b border-pedago-line bg-pedago-canvas/40 text-[10.5px] uppercase tracking-wider text-pedago-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold">
                            <a class="inline-flex items-center gap-1 hover:text-pedago-ink" href="{{ request()->fullUrlWithQuery(['sort' => $sort === 'name_asc' ? 'name_desc' : 'name_asc']) }}">
                                Lead
                                <span class="text-[9px]">↕</span>
                            </a>
                        </th>
                        <th class="px-3 py-3 font-semibold">Phone</th>
                        <th class="px-3 py-3 font-semibold">Subject / Topic</th>
                        <th class="px-3 py-3 font-semibold">Course</th>
                        <th class="px-3 py-3 font-semibold">Campaign</th>
                        <th class="px-3 py-3 font-semibold">
                            <a class="inline-flex items-center gap-1 hover:text-pedago-ink" href="{{ request()->fullUrlWithQuery(['sort' => 'status']) }}">
                                Status
                                <span class="text-[9px]">↕</span>
                            </a>
                        </th>
                        <th class="px-3 py-3 font-semibold">assignd (owner)</th>
                        <th class="px-3 py-3 font-semibold">
                            <a class="inline-flex items-center gap-1 hover:text-pedago-ink" href="{{ request()->fullUrlWithQuery(['sort' => 'last_contact']) }}">
                                Last contact
                                <span class="text-[9px]">↕</span>
                            </a>
                        </th>
                        <th class="px-3 py-3 font-semibold">
                            <a class="inline-flex items-center gap-1 hover:text-pedago-ink" href="{{ request()->fullUrlWithQuery(['sort' => $sort === 'oldest' ? 'newest' : 'oldest']) }}">
                                Created
                                <span class="text-[9px]">↕</span>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pedago-line text-xs">
                    @forelse ($leads as $lead)
                        @php
                            $statusPillClasses = match($lead->status) {
                                'New' => 'bg-sky-50 text-sky-800 border-sky-200',
                                'Assigned' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                'Follow-up' => 'bg-amber-50 text-amber-900 border-amber-200',
                                'Interested' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                'Enrolled - Pending Payment', 'Payment Under Review' => 'bg-purple-50 text-purple-800 border-purple-200',
                                'Converted' => 'bg-emerald-100 text-emerald-900 border-emerald-300 font-bold',
                                'Not Interested', 'Unreachable' => 'bg-slate-100 text-slate-700 border-slate-200',
                                'Lost' => 'bg-rose-50 text-rose-800 border-rose-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                            $statusDotClasses = match($lead->status) {
                                'New' => 'bg-sky-500',
                                'Assigned' => 'bg-indigo-500',
                                'Follow-up' => 'bg-amber-500',
                                'Interested' => 'bg-emerald-500',
                                'Enrolled - Pending Payment', 'Payment Under Review' => 'bg-purple-500',
                                'Converted' => 'bg-emerald-600',
                                'Not Interested', 'Unreachable' => 'bg-slate-400',
                                'Lost' => 'bg-rose-500',
                                default => 'bg-slate-400',
                            };
                            $displayStatus = match($lead->status) {
                                'Enrolled - Pending Payment' => 'Pending payment',
                                'Not Interested' => 'Not interested',
                                default => $lead->status,
                            };
                        @endphp
                        <tr class="transition-colors hover:bg-pedago-canvas/60">
                            {{-- 1. Lead --}}
                            <td class="px-4 py-3">
                                <span class="font-bold text-pedago-ink">{{ $lead->student_name }}</span>
                                <br>
                                <span class="font-mono text-[11px] text-pedago-muted">L-{{ $lead->id }}</span>
                            </td>

                            {{-- 2. Phone --}}
                            <td class="px-3 py-3">
                                <span class="font-mono text-xs text-pedago-ink">{{ $lead->contact_number }}</span>
                                @if (filled($lead->whatsapp_number) || filled($lead->alternative_contact_number))
                                    <br>
                                    <span class="font-mono text-[11px] text-pedago-muted">
                                        {{ filled($lead->whatsapp_number) ? $lead->whatsapp_number : $lead->alternative_contact_number }}
                                    </span>
                                @endif
                            </td>

                            {{-- 3. Subject / Topic --}}
                            <td class="px-3 py-3">
                                @if ($lead->interestedCourse?->subject)
                                    <span class="font-semibold text-pedago-ink">{{ $lead->interestedCourse->subject->name }}</span>
                                    <br>
                                    <span class="text-[11px] text-pedago-muted">{{ $lead->interestedCourse?->topic?->name ?? 'All topics' }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                        Not linked
                                    </span>
                                @endif
                            </td>

                            {{-- 4. Course --}}
                            <td class="px-3 py-3">
                                <span class="text-pedago-ink">{{ $lead->interestedCourse?->title ?? '—' }}</span>
                            </td>

                            {{-- 5. Campaign --}}
                            <td class="px-3 py-3">
                                <span class="font-medium text-pedago-ink">{{ $lead->source?->campaign_name ?? '—' }}</span>
                                @if ($lead->source?->channel)
                                    <br>
                                    <span class="text-[11px] text-pedago-muted">{{ App\Models\LeadSource::CHANNELS[$lead->source->channel] ?? ucfirst($lead->source->channel) }}</span>
                                @endif
                            </td>

                            {{-- 6. Status --}}
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusPillClasses }}">
                                    <span class="size-1.5 rounded-full {{ $statusDotClasses }}"></span>
                                    {{ $displayStatus }}
                                </span>
                            </td>

                            {{-- 7. assignd (owner) --}}
                            <td class="px-3 py-3">
                                @if ($lead->currentOwner)
                                    <div class="leading-tight">
                                        <span class="font-medium text-pedago-ink">{{ $lead->currentOwner->name }}</span>
                                        <span class="font-mono text-[11px] text-pedago-muted">({{ $lead->currentOwner->office_id }})</span>
                                        <br>
                                        <span class="text-[11px] text-pedago-muted">{{ $lead->team?->name ?? $lead->currentOwner->team?->name ?? 'No team' }}</span>
                                    </div>
                                @else
                                    @if ($canAssign)
                                        <form method="POST" action="{{ route('leads.assign', $lead) }}" class="inline-flex items-center">
                                            @csrf
                                            <select name="employee_id" onchange="this.form.submit()" class="h-7 w-44 rounded-md border border-pedago-line bg-white px-2 py-0.5 text-xs font-medium text-pedago-ink shadow-2xs hover:border-pedago-blue focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue cursor-pointer" title="Assign lead to sales person">
                                                <option value="" disabled selected>Assign to...</option>
                                                @foreach ($salesPersons as $sp)
                                                    <option value="{{ $sp->id }}">({{ $sp->office_id }} - {{ $sp->name }})</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="text-xs text-pedago-muted italic">Unassigned</span>
                                    @endif
                                @endif
                            </td>

                            {{-- 8. Last contact & Overdue follow-up date --}}
                            <td class="px-3 py-3">
                                <span class="text-xs text-pedago-muted">{{ $lead->last_action_at ? $lead->last_action_at->format('d M, g:i A') : '—' }}</span>
                                @if ($lead->latestPendingFollowup)
                                    @php
                                        $isOverdue = $lead->latestPendingFollowup->followup_date_time->isPast();
                                    @endphp
                                    <br>
                                    <span @class([
                                        'inline-flex items-center gap-1 font-mono text-[11px]',
                                        'font-semibold text-rose-600' => $isOverdue,
                                        'text-pedago-muted' => ! $isOverdue,
                                    ]) title="{{ $isOverdue ? 'Overdue follow-up date' : 'Scheduled follow-up date' }}">
                                        @if ($isOverdue)
                                            <svg class="size-3 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                            </svg>
                                            Overdue: {{ $lead->latestPendingFollowup->followup_date_time->format('d M, g:i A') }}
                                        @else
                                            Next: {{ $lead->latestPendingFollowup->followup_date_time->format('d M, g:i A') }}
                                        @endif
                                    </span>
                                @endif
                            </td>

                            {{-- 9. Created --}}
                            <td class="px-3 py-3 text-pedago-muted">
                                {{ $lead->created_at->format('d M') }}
                            </td>

                            {{-- 10. Actions (Eye icon ONLY, no edit btn) --}}
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="inline-flex size-8 items-center justify-center rounded-md border border-pedago-line bg-white text-pedago-muted shadow-2xs transition hover:border-pedago-blue hover:text-pedago-blue focus-visible:outline-2 focus-visible:outline-pedago-blue" title="View lead details" onclick="showLeadDetails({{ json_encode([
                                    'id' => $lead->id,
                                    'code' => 'L-' . $lead->id,
                                    'student_name' => $lead->student_name,
                                    'guardian_name' => $lead->guardian_name ?? '—',
                                    'contact_number' => $lead->contact_number,
                                    'whatsapp_number' => $lead->whatsapp_number ?? '—',
                                    'alternative_contact_number' => $lead->alternative_contact_number ?? '—',
                                    'email' => $lead->email ?? '—',
                                    'location' => $lead->location ?? '—',
                                    'age' => $lead->age ? (string) $lead->age : '—',
                                    'class' => $lead->class ?? '—',
                                    'student_parent_type' => $lead->student_parent_type ?? '—',
                                    'student_background' => $lead->student_background ?? '—',
                                    'course' => $lead->interestedCourse?->title ?? '—',
                                    'subject' => $lead->interestedCourse?->subject?->name ?? '—',
                                    'topic' => $lead->interestedCourse?->topic?->name ?? '—',
                                    'campaign' => $lead->source?->campaign_name ?? '—',
                                    'channel' => $lead->source?->channel ? (App\Models\LeadSource::CHANNELS[$lead->source->channel] ?? ucfirst($lead->source->channel)) : '—',
                                    'status' => $lead->status,
                                    'owner' => $lead->currentOwner ? "({$lead->currentOwner->office_id} - {$lead->currentOwner->name})" : 'Unassigned',
                                    'team' => $lead->team?->name ?? $lead->currentOwner?->team?->name ?? '—',
                                    'last_contact' => $lead->last_action_at ? $lead->last_action_at->format('d M Y, g:i A') : '—',
                                    'followup_date' => $lead->latestPendingFollowup ? $lead->latestPendingFollowup->followup_date_time->format('d M Y, g:i A') : '—',
                                    'is_overdue' => $lead->latestPendingFollowup ? $lead->latestPendingFollowup->followup_date_time->isPast() : false,
                                    'created_at' => $lead->created_at->format('d M Y, g:i A'),
                                    'is_unassigned' => $lead->current_owner_id === null,
                                ]) }})">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <span class="sr-only">View lead</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-pedago-muted">
                                <p class="text-sm font-medium">No leads found matching your criteria.</p>
                                <p class="mt-1 text-xs">Try adjusting your filters or search keyword.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer & Pagination --}}
        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-pedago-line px-5 py-4">
            <p class="text-xs text-pedago-muted">
                Leads are never deleted. Close one as Lost or Not interested through call feedback; its history stays.
            </p>
            <div>
                {{ $leads->links() }}
            </div>
        </div>
    </section>

    {{-- 4. Lead Details Modal (Opened via Eye Icon) --}}
    <dialog id="lead-detail-modal" class="m-auto max-h-[92vh] w-[min(94vw,42rem)] overflow-y-auto rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-bold text-pedago-ink">Lead Details</h2>
                <span id="dt-code" class="rounded bg-pedago-tint px-2 py-0.5 font-mono text-xs font-semibold text-pedago-blue-dark"></span>
            </div>
            <button class="rounded-md p-1.5 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink focus-visible:outline-2 focus-visible:outline-pedago-blue" type="button" data-close-dialog aria-label="Close dialog">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-4 p-5 text-xs">
            {{-- Student & Parent Section --}}
            <div class="rounded-lg border border-pedago-line bg-pedago-canvas/30 p-3.5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-pedago-muted mb-2">Student & Contact Information</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <span class="text-pedago-muted block">Student Name</span>
                        <strong id="dt-name" class="text-sm font-semibold text-pedago-ink"></strong>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Guardian Name</span>
                        <span id="dt-guardian" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Relation / Type</span>
                        <span id="dt-type" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Primary Phone</span>
                        <span id="dt-phone" class="font-mono font-semibold text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">WhatsApp</span>
                        <span id="dt-whatsapp" class="font-mono text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Alternative Phone</span>
                        <span id="dt-altphone" class="font-mono text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Email</span>
                        <span id="dt-email" class="text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Location / City</span>
                        <span id="dt-location" class="text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Age & Class</span>
                        <span id="dt-ageclass" class="text-pedago-ink"></span>
                    </div>
                </div>
            </div>

            {{-- Academic & Campaign Section --}}
            <div class="rounded-lg border border-pedago-line bg-pedago-canvas/30 p-3.5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-pedago-muted mb-2">Academic Course & Marketing Source</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <span class="text-pedago-muted block">Course</span>
                        <strong id="dt-course" class="font-semibold text-pedago-ink"></strong>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Subject</span>
                        <span id="dt-subject" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Topic</span>
                        <span id="dt-topic" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Campaign</span>
                        <span id="dt-campaign" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Channel</span>
                        <span id="dt-channel" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Current Status</span>
                        <span id="dt-status" class="inline-flex items-center rounded-full bg-pedago-tint px-2 py-0.5 font-semibold text-pedago-blue-dark"></span>
                    </div>
                </div>
            </div>

            {{-- Assignment & Activity Section --}}
            <div class="rounded-lg border border-pedago-line bg-pedago-canvas/30 p-3.5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-pedago-muted mb-2">Assignment & History</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <span class="text-pedago-muted block">assignd (owner)</span>
                        <strong id="dt-owner" class="font-semibold text-pedago-ink"></strong>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Assigned Team</span>
                        <span id="dt-team" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Last Contact</span>
                        <span id="dt-lastcontact" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Follow-up Date</span>
                        <span id="dt-followup" class="font-medium text-pedago-ink"></span>
                    </div>
                    <div>
                        <span class="text-pedago-muted block">Created At</span>
                        <span id="dt-created" class="font-medium text-pedago-ink"></span>
                    </div>
                </div>
            </div>

            {{-- Background / Notes Section --}}
            <div class="rounded-lg border border-pedago-line bg-pedago-canvas/30 p-3.5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-pedago-muted mb-1">Student Background / Notes</p>
                <p id="dt-background" class="text-xs text-pedago-ink whitespace-pre-wrap leading-relaxed">—</p>
            </div>
        </div>

        <div class="flex items-center justify-end border-t border-pedago-line px-5 py-3">
            <button class="rounded-md border border-pedago-line bg-white px-4 py-2 text-xs font-semibold text-pedago-ink transition hover:bg-pedago-canvas" type="button" data-close-dialog>
                Close
            </button>
        </div>
    </dialog>

    {{-- 5. Add Lead Modal --}}
    @if ($canAssign || in_array($currentEmployee->role, ['Supervisor', 'Team Leader'], true))
        <dialog id="lead-create-modal" class="m-auto max-h-[92vh] w-[min(94vw,38rem)] overflow-y-auto rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
            <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
                <div>
                    <h2 class="text-base font-bold text-pedago-ink">Add New Lead</h2>
                    <p class="text-xs text-pedago-muted">Enter student and contact details to register a new lead.</p>
                </div>
                <button class="rounded-md p-1.5 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink focus-visible:outline-2 focus-visible:outline-pedago-blue" type="button" data-close-dialog aria-label="Close dialog">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('leads.quick-create') }}" class="space-y-4 p-5 text-xs">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {{-- Student Name --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_student_name">Student Name <span class="text-rose-500">*</span></label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_student_name" name="student_name" type="text" required placeholder="e.g. Mehedi Hasan">
                    </div>

                    {{-- Contact Number --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_contact_number">Primary Phone <span class="text-rose-500">*</span></label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 font-mono text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_contact_number" name="contact_number" type="text" required placeholder="017xxxxxxxx">
                    </div>

                    {{-- Guardian Name --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_guardian_name">Guardian Name</label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_guardian_name" name="guardian_name" type="text" placeholder="Parent or guardian name">
                    </div>

                    {{-- WhatsApp Number --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_whatsapp_number">WhatsApp Number</label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 font-mono text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_whatsapp_number" name="whatsapp_number" type="text" placeholder="018xxxxxxxx">
                    </div>

                    {{-- Alternative Contact Number --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_alt_contact">Alternative Phone</label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 font-mono text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_alt_contact" name="alternative_contact_number" type="text" placeholder="Second contact number">
                    </div>

                    {{-- Email --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_email">Email</label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_email" name="email" type="email" placeholder="student@example.com">
                    </div>

                    {{-- Location --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_location">Location / Area</label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_location" name="location" type="text" placeholder="City or district">
                    </div>

                    {{-- Class --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_class">Class / Grade</label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_class" name="class" type="text" placeholder="e.g. Class 10, HSC, University">
                    </div>

                    {{-- Channel --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_channel">Channel <span class="text-rose-500">*</span></label>
                        <select class="h-9 rounded-md border border-pedago-line bg-white px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_channel" name="channel" required>
                            @foreach ($channels as $code => $chName)
                                <option value="{{ $code }}">{{ $chName }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Campaign Name --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_campaign">Campaign Name</label>
                        <input class="h-9 rounded-md border border-pedago-line bg-white px-3 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_campaign" name="campaign_name" type="text" placeholder="e.g. Admission Nov · Reels">
                    </div>

                    {{-- Interested Course --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_course">Interested Course</label>
                        <select class="h-9 rounded-md border border-pedago-line bg-white px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_course" name="interested_course_id">
                            <option value="">Select course (optional)</option>
                            @foreach ($courses as $c)
                                <option value="{{ $c->id }}">{{ $c->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Direct Assignment --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold text-pedago-muted" for="c_assign">assignd (owner)</label>
                        <select class="h-9 rounded-md border border-pedago-line bg-white px-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_assign" name="assign_employee_id">
                            <option value="">Leave Unassigned</option>
                            @foreach ($salesPersons as $sp)
                                <option value="{{ $sp->id }}">({{ $sp->office_id }} - {{ $sp->name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Notes / Background --}}
                <div class="flex flex-col gap-1">
                    <label class="font-semibold text-pedago-muted" for="c_background">Student Background / Notes</label>
                    <textarea class="rounded-md border border-pedago-line bg-white p-2.5 text-xs text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-1 focus:ring-pedago-blue" id="c_background" name="student_background" rows="3" placeholder="Additional notes about student expectations, target exam, etc."></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-pedago-line pt-4">
                    <button class="rounded-md border border-pedago-line bg-white px-4 py-2 text-xs font-semibold text-pedago-ink transition hover:bg-pedago-canvas" type="button" data-close-dialog>
                        Cancel
                    </button>
                    <button class="rounded-md bg-pedago-blue px-4 py-2 text-xs font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-pedago-blue" type="submit">
                        Create lead
                    </button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Script for Dialogs and Eye-Icon Details Modal --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const detailModal = document.getElementById('lead-detail-modal');

            // Open Dialog Buttons
            document.querySelectorAll('[data-open-dialog]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-open-dialog');
                    const dlg = document.getElementById(id);
                    if (dlg) {
                        dlg.showModal();
                    }
                });
            });

            // Close Dialog Buttons
            document.querySelectorAll('[data-close-dialog]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const dlg = btn.closest('dialog');
                    if (dlg) {
                        dlg.close();
                    }
                });
            });

            // Click outside dialog to close
            document.querySelectorAll('dialog').forEach((dlg) => {
                dlg.addEventListener('click', (e) => {
                    if (e.target === dlg) {
                        dlg.close();
                    }
                });
            });

            // Function to populate and open lead details modal
            window.showLeadDetails = function(data) {
                if (!detailModal) return;

                document.getElementById('dt-code').textContent = data.code || ('L-' + data.id);
                document.getElementById('dt-name').textContent = data.student_name || '—';
                document.getElementById('dt-guardian').textContent = data.guardian_name || '—';
                document.getElementById('dt-type').textContent = data.student_parent_type || '—';
                document.getElementById('dt-phone').textContent = data.contact_number || '—';
                document.getElementById('dt-whatsapp').textContent = data.whatsapp_number || '—';
                document.getElementById('dt-altphone').textContent = data.alternative_contact_number || '—';
                document.getElementById('dt-email').textContent = data.email || '—';
                document.getElementById('dt-location').textContent = data.location || '—';
                document.getElementById('dt-ageclass').textContent = (data.age !== '—' ? (data.age + ' yrs, ') : '') + (data.class || '—');
                document.getElementById('dt-course').textContent = data.course || '—';
                document.getElementById('dt-subject').textContent = data.subject || '—';
                document.getElementById('dt-topic').textContent = data.topic || '—';
                document.getElementById('dt-campaign').textContent = data.campaign || '—';
                document.getElementById('dt-channel').textContent = data.channel || '—';
                document.getElementById('dt-status').textContent = data.status || '—';
                document.getElementById('dt-owner').textContent = data.owner || '—';
                document.getElementById('dt-team').textContent = data.team || '—';
                document.getElementById('dt-lastcontact').textContent = data.last_contact || '—';
                const followupEl = document.getElementById('dt-followup');
                if (followupEl) {
                    followupEl.textContent = data.followup_date || '—';
                    if (data.is_overdue) {
                        followupEl.className = 'font-semibold text-rose-600';
                        if (data.followup_date && data.followup_date !== '—') {
                            followupEl.textContent += ' (OVERDUE)';
                        }
                    } else {
                        followupEl.className = 'font-medium text-pedago-ink';
                    }
                }
                document.getElementById('dt-created').textContent = data.created_at || '—';
                document.getElementById('dt-background').textContent = data.student_background || '—';

                detailModal.showModal();
            };
        });
    </script>
@endsection

