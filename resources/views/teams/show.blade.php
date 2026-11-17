@extends('layouts.app', ['title' => $team->name . ' · Team details'])

@section('page-title', 'Team details')
@section('page-description', 'Performance metrics, pipeline status, and executive roster for ' . $team->name . '.')
@section('page-actions')
    <a class="inline-flex items-center justify-center rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('teams.index') }}">
        ← Back to teams
    </a>
@endsection

@section('content')
    {{-- 1. Team Header Banner --}}
    <section class="rounded-lg border border-pedago-line bg-white p-5 sm:p-6" aria-labelledby="team-header-heading">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-pedago-tint text-xl font-bold text-pedago-blue-dark shadow-inner" aria-hidden="true">
                    {{ strtoupper(substr($team->name, 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-pedago-ink" id="team-header-heading">{{ $team->name }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-semibold text-[11px]',
                            'bg-pedago-green-tint text-pedago-green' => $team->is_active,
                            'bg-pedago-slate-tint text-pedago-muted' => ! $team->is_active,
                        ])>
                            <span aria-hidden="true">●</span> {{ $team->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-pedago-tint px-2.5 py-0.5 text-[11px] font-semibold text-pedago-blue-dark">
                            Leader:
                            @if ($team->teamLeader)
                                @php
                                    $leaderAvail = $team->teamLeader->effectiveAvailabilityStatus();
                                @endphp
                                <span>{{ $team->teamLeader->name }} ({{ $team->teamLeader->office_id ?? '#'.$team->teamLeader->id }})</span>
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.2 text-[10px] font-semibold',
                                    'bg-pedago-green-tint text-pedago-green' => $leaderAvail === 'Available',
                                    'bg-blue-100 text-blue-800' => $leaderAvail === 'Busy',
                                    'bg-purple-100 text-pedago-blue-dark' => $leaderAvail === 'Cut Off',
                                    'bg-pedago-amber-tint text-pedago-amber' => $leaderAvail === 'Holiday',
                                    'bg-pedago-slate-tint text-pedago-muted' => in_array($leaderAvail, ['Off Duty', 'Off duty'], true),
                                    'bg-pedago-amber-tint text-pedago-amber' => in_array($leaderAvail, ['Unavailable', 'Break'], true),
                                    'bg-pedago-red-tint text-pedago-red' => $leaderAvail === 'Lazy',
                                    'bg-pedago-slate-tint text-pedago-muted' => $leaderAvail === 'Resigned',
                                ])>
                                    <span @class([
                                        'size-1.5 rounded-full',
                                        'bg-pedago-green' => $leaderAvail === 'Available',
                                        'bg-blue-500' => $leaderAvail === 'Busy',
                                        'bg-pedago-blue-soft' => $leaderAvail === 'Cut Off',
                                        'bg-pedago-amber' => $leaderAvail === 'Holiday',
                                        'bg-pedago-muted' => in_array($leaderAvail, ['Off Duty', 'Off duty'], true),
                                        'bg-pedago-amber' => in_array($leaderAvail, ['Unavailable', 'Break'], true),
                                        'bg-pedago-red' => $leaderAvail === 'Lazy',
                                        'bg-pedago-muted' => $leaderAvail === 'Resigned',
                                    ])></span>
                                    {{ $leaderAvail }}
                                </span>
                            @else
                                None
                            @endif
                        </span>
                        <span class="rounded-full bg-pedago-tint px-2.5 py-0.5 text-[11px] font-semibold text-pedago-blue-dark">
                            Supervisor:
                            @forelse ($team->supervisors as $sup)
                                {{ $sup->name }} ({{ $sup->office_id ?? '#'.$sup->id }})@if (! $loop->last), @endif
                            @empty
                                None
                            @endforelse
                        </span>
                        <span class="rounded-full bg-pedago-tint px-2.5 py-0.5 text-[11px] font-semibold text-pedago-blue-dark">
                            Duty time:
                            @if ($team->shift)
                                {{ \Carbon\Carbon::parse($team->shift->shift_start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($team->shift->shift_end_time)->format('h:i A') }} ({{ $team->shift->working_hours }}h)
                            @else
                                Not set
                            @endif
                            @if (($stats['overwritten_count'] ?? 0) > 0)
                                <span class="ml-1 rounded-full bg-pedago-amber-tint px-1.5 py-0.5 text-[10px] font-semibold text-pedago-amber border border-pedago-amber/30">
                                    overwrite: {{ $stats['overwritten_count'] }}
                                </span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button class="inline-flex items-center justify-center rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-ink transition hover:bg-pedago-canvas" type="button" data-open-edit>
                    Edit team
                </button>
                <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="button" data-open-edit>
                    Add members
                </button>
            </div>
        </div>
    </section>

    {{-- 2. Team KPIs --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Team metrics">
        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3">
            <p class="text-xs text-pedago-muted">Members</p>
            <p class="mt-1 text-2xl font-bold leading-none text-pedago-ink">{{ number_format($stats['members']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">{{ $stats['available_now'] }} available now</p>
        </article>

        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3">
            <p class="text-xs text-pedago-muted">Open leads</p>
            <p class="mt-1 text-2xl font-bold leading-none text-pedago-ink">{{ number_format($stats['open_leads']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">{{ $stats['in_active_batches'] }} in active batches</p>
        </article>

        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3">
            <p class="text-xs text-pedago-muted">Conversion (30d)</p>
            <p class="mt-1 text-2xl font-bold leading-none text-pedago-ink">{{ $stats['conversion_rate_30d'] }}%</p>
            <p class="mt-1 text-[11px] text-pedago-muted">{{ $stats['converted_count_30d'] }} of {{ $stats['total_leads_30d'] }} leads</p>
        </article>

        <article @class([
            'rounded-lg border bg-white px-4 py-3',
            'border-pedago-red-line' => $stats['overdue_followups'] > 0,
            'border-pedago-line' => $stats['overdue_followups'] === 0,
        ])>
            <p class="text-xs text-pedago-muted">Overdue follow-ups</p>
            <p @class([
                'mt-1 text-2xl font-bold leading-none',
                'text-pedago-red' => $stats['overdue_followups'] > 0,
                'text-pedago-ink' => $stats['overdue_followups'] === 0,
            ])>{{ number_format($stats['overdue_followups']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">{{ $stats['lazy_executives'] }} executives Lazy</p>
        </article>

        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3">
            <p class="text-xs text-pedago-muted">Pending transfers</p>
            <p class="mt-1 text-2xl font-bold leading-none text-pedago-ink">{{ number_format($stats['pending_transfers_in'] + $stats['pending_transfers_out']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">{{ $stats['pending_transfers_in'] }} in, {{ $stats['pending_transfers_out'] }} out</p>
        </article>
    </section>

    {{-- 3. Main Grid: Members Table + Right Column Cards --}}
    <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
        {{-- Left: Members Table --}}
        <section class="overflow-hidden rounded-lg border border-pedago-line bg-white" aria-labelledby="members-heading">
            <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
                <h2 class="text-sm font-bold text-pedago-ink" id="members-heading">Team Roster &amp; Members</h2>
                <span class="text-xs text-pedago-muted">Showing {{ $rosterStaff->count() }} of {{ $rosterStaff->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] border-collapse text-left text-xs">
                    <thead class="border-b border-pedago-line bg-pedago-canvas/40 text-[10px] uppercase tracking-wide text-pedago-muted">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Staff Member</th>
                            <th class="px-3 py-3 font-semibold">Shift / Duty Time</th>
                            <th class="px-3 py-3 font-semibold">Availability Status</th>
                            <th class="px-3 py-3 font-semibold">Batch</th>
                            <th class="px-3 py-3 font-semibold">Overdue</th>
                            <th class="px-3 py-3 font-semibold">Conversion (30d)</th>
                            <th class="px-4 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pedago-line">
                        @forelse ($rosterStaff as $staff)
                            @php($mStat = $memberStats[$staff->id] ?? [])
                            @php($isLeader = ! empty($mStat['is_leader']))
                            <tr class="transition-colors hover:bg-pedago-canvas/60">
                                <td class="px-4 py-3 font-semibold text-pedago-ink">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ $staff->name }}</span>
                                        @if ($isLeader)
                                            <span class="rounded bg-pedago-tint px-1.5 py-0.5 text-[10px] font-semibold text-pedago-blue-dark">Leader</span>
                                        @else
                                            <span class="rounded bg-pedago-slate-tint px-1.5 py-0.5 text-[10px] font-semibold text-pedago-muted">Executive</span>
                                        @endif
                                    </div>
                                    <div class="num text-[11px] font-normal text-pedago-muted">{{ $staff->office_id ?? '#'.$staff->id }}</div>
                                </td>
                                <td class="px-3 py-3 text-pedago-muted">
                                    <div class="flex items-center gap-1.5">
                                        <span class="num text-xs font-semibold text-pedago-ink">{{ $mStat['shift'] ?? 'Day off' }}</span>
                                        @if (! empty($mStat['is_overridden']))
                                            <span class="rounded bg-pedago-tint px-1.5 py-0.5 text-[9px] font-semibold text-pedago-blue-dark border border-pedago-line" title="Specific date override">Override</span>
                                        @elseif ($team->shift)
                                            <span class="rounded bg-pedago-tint px-1.5 py-0.5 text-[9px] font-semibold text-pedago-blue-dark border border-pedago-line" title="Inherited from team duty time">Team</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    @php($st = $mStat['status'] ?? 'Off Duty')
                                    <span @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-pedago-green-tint text-pedago-green' => $st === 'Available',
                                        'bg-blue-50 text-blue-800' => $st === 'Busy',
                                        'bg-pedago-tint text-pedago-blue-dark' => $st === 'Cut Off',
                                        'bg-pedago-amber-tint text-pedago-amber' => $st === 'Holiday',
                                        'bg-pedago-slate-tint text-pedago-muted' => in_array($st, ['Off Duty', 'Off duty'], true),
                                        'bg-pedago-red-tint text-pedago-red' => $st === 'Lazy',
                                        'bg-pedago-amber-tint text-pedago-amber' => in_array($st, ['Unavailable', 'Break'], true),
                                        'bg-pedago-slate-tint text-pedago-muted' => $st === 'Resigned',
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-pedago-green' => $st === 'Available',
                                            'bg-blue-500' => $st === 'Busy',
                                            'bg-pedago-blue-soft' => $st === 'Cut Off',
                                            'bg-pedago-amber' => $st === 'Holiday',
                                            'bg-pedago-muted' => in_array($st, ['Off Duty', 'Off duty'], true),
                                            'bg-pedago-red' => $st === 'Lazy',
                                            'bg-pedago-amber' => in_array($st, ['Unavailable', 'Break'], true),
                                            'bg-pedago-muted' => $st === 'Resigned',
                                        ])></span>
                                        {{ $st }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 num">
                                    {{ $mStat['batch'] ?? '—' }}
                                </td>
                                <td class="px-3 py-3 num">
                                    @if (($mStat['overdue'] ?? 0) > 0)
                                        <span class="font-bold text-pedago-red">{{ $mStat['overdue'] }}</span>
                                    @else
                                        <span>0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 num">
                                    {{ $mStat['conversion_rate_30d'] ?? 0 }}%
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        {{-- View employee profile & calendar override icon --}}
                                        <a class="inline-flex size-7 items-center justify-center rounded-md border border-pedago-line text-pedago-ink transition hover:border-pedago-blue hover:bg-pedago-tint hover:text-pedago-blue-dark" href="{{ route('employees.edit', $staff) }}" title="View profile & schedule" aria-label="View {{ $staff->name }}">
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                                                <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </a>

                                        @if (! $isLeader)
                                            {{-- Remove member from team icon --}}
                                            <button class="inline-flex size-7 items-center justify-center rounded-md border border-pedago-red-line text-pedago-red transition hover:bg-pedago-red-tint" type="button" title="Remove member" aria-label="Remove {{ $staff->name }} from {{ $team->name }}"
                                                data-remove-member
                                                data-id="{{ $staff->id }}"
                                                data-name="{{ $staff->name }}"
                                                data-office-id="{{ $staff->office_id ?? '#'.$staff->id }}">
                                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-8 text-center text-pedago-muted" colspan="7">No members currently in this team.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Right: Executive Performance + Pipeline Funnel --}}
        <aside class="flex flex-col gap-4">
            {{-- Performance by executive card --}}
            <section class="rounded-lg border border-pedago-line bg-white p-4" aria-labelledby="perf-heading">
                <div class="flex items-center justify-between border-b border-pedago-line pb-3">
                    <h2 class="text-sm font-bold text-pedago-ink" id="perf-heading">Performance by executive</h2>
                    <span class="text-[11px] text-pedago-muted">Last 30 days</span>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($executivePerformance as $exec)
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-pedago-ink">{{ $exec['name'] }}</span>
                                <span class="num font-bold text-pedago-ink">{{ $exec['conversions'] }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-pedago-canvas">
                                <div class="h-full rounded-full bg-pedago-blue transition-all" style="width: {{ $exec['width_percent'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-pedago-muted">No conversions recorded for team members.</p>
                    @endforelse
                </div>
                <p class="mt-4 border-t border-pedago-line pt-3 text-[11px] text-pedago-muted">
                    Conversions per executive, last 30 days.
                </p>
            </section>

            {{-- Team pipeline card --}}
            <section class="rounded-lg border border-pedago-line bg-white p-4" aria-labelledby="pipeline-heading">
                <div class="flex items-center justify-between border-b border-pedago-line pb-3">
                    <h2 class="text-sm font-bold text-pedago-ink" id="pipeline-heading">Team pipeline</h2>
                    <span class="text-[11px] text-pedago-muted">Open leads</span>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($pipeline as $item)
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-pedago-ink">{{ $item['stage'] }}</span>
                                <span class="num font-bold text-pedago-ink">{{ number_format($item['count']) }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-pedago-canvas">
                                <div @class([
                                    'h-full rounded-full transition-all',
                                    'bg-pedago-blue' => $item['stage'] === 'Follow-up' || $item['stage'] === 'Interested',
                                    'bg-pedago-amber' => $item['stage'] === 'Pending payment',
                                    'bg-pedago-green' => $item['stage'] === 'Converted',
                                ]) style="width: {{ $item['width_percent'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>

    {{-- Edit Team Modal --}}
    <dialog id="team-edit-modal" class="m-auto max-h-[92vh] w-[min(94vw,34rem)] overflow-y-auto rounded-lg border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-pedago-ink/45">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-base font-bold">Edit team {{ $team->name }}</h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <form class="flex flex-col gap-5 p-5" method="POST" action="{{ route('teams.update', $team) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_active" id="edit-is-active" value="{{ $team->is_active ? '1' : '0' }}">

            {{-- Team section --}}
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Team</span>
                <label class="mt-2 flex flex-col gap-1.5 text-xs font-semibold" for="edit-team-name">
                    Team name <span class="text-pedago-red">*</span>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-team-name" maxlength="100" name="name" type="text" value="{{ old('name', $team->name) }}" required>
                </label>
            </div>

            {{-- Primary Duty Time (Shift) --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Primary Duty Time (Shift)</span>
                    <span class="rounded bg-pedago-tint px-2 py-0.5 text-[11px] font-semibold text-pedago-blue-dark">Inherited by members</span>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-modal-shift-id">
                            Predefined shift
                            <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-modal-shift-id" name="shift_id">
                                <option value="">-- No fixed shift --</option>
                                @foreach ($shifts as $s)
                                    <option value="{{ $s->id }}" @selected((string) old('shift_id', $team->shift_id) === (string) $s->id)>
                                        {{ \Carbon\Carbon::parse($s->shift_start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($s->shift_end_time)->format('h:i A') }} ({{ $s->working_hours }}h)
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div>
                        <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-modal-shift-start">
                            Or custom start
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-modal-shift-start" name="shift_start_time" type="time" value="{{ old('shift_start_time') }}">
                        </label>
                    </div>
                    <div>
                        <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-modal-shift-end">
                            Custom end
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-modal-shift-end" name="shift_end_time" type="time" value="{{ old('shift_end_time') }}">
                        </label>
                    </div>
                </div>
                @if ($team->shift)
                    <p class="text-[11px] text-pedago-green">Current primary duty time: <strong>{{ \Carbon\Carbon::parse($team->shift->shift_start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($team->shift->shift_end_time)->format('h:i A') }}</strong> ({{ $team->shift->working_hours }} hrs).</p>
                @endif
                <p class="text-[11px] text-pedago-muted">All team members will primarily inherit this duty time. Specific dates can be overridden from employee details calendar.</p>
            </div>

            {{-- Leadership section --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Leadership</span>
                <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-team-leader">
                    Team leader <span class="text-pedago-red">*</span>
                    <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-team-leader" name="team_leader_id" required>
                        <option value="">Select a leader</option>
                        @foreach ($potentialLeaders as $ldr)
                            @php($otherTeam = $ldr->teamsLed->first())
                            @php($leadsOther = $otherTeam !== null && $otherTeam->id !== $team->id)
                            <option value="{{ $ldr->id }}" @selected((string) old('team_leader_id', $team->team_leader_id) === (string) $ldr->id) @disabled($leadsOther)>
                                {{ $ldr->name }} ({{ $ldr->office_id ?? '#'.$ldr->id }}) - {{ $ldr->role }}
                                @if ($leadsOther)
                                    — Already leads {{ $otherTeam->name }} (Unavailable)
                                @endif
                            </option>
                        @endforeach
                    </select>
                </label>
                <p class="text-[11px] text-pedago-muted">A leader can only lead one team at a time.</p>

                <fieldset class="mt-1 flex flex-col gap-2">
                    <legend class="text-xs font-semibold">Supervisors</legend>
                    <div class="flex flex-col gap-1.5 rounded-md border border-pedago-line bg-pedago-canvas/30 p-2.5">
                        @foreach ($supervisors as $sup)
                            <label class="flex items-center gap-2 text-xs">
                                <input class="size-4 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="supervisors[]" value="{{ $sup->id }}" @checked(in_array($sup->id, old('supervisors', $team->supervisors->pluck('id')->all()), false))>
                                <span class="font-medium text-pedago-ink">{{ $sup->name }} <span class="num text-[11px] text-pedago-muted">({{ $sup->office_id ?? '#'.$sup->id }})</span></span>
                                <small class="text-pedago-muted">Supervises {{ $sup->supervisedTeams->pluck('name')->join(', ') ?: 'no teams' }}</small>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-pedago-muted">A supervisor can oversee several teams.</p>
                </fieldset>
            </div>

            {{-- Members section --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Members</span>
                <div class="flex flex-col gap-1.5 text-xs font-semibold">
                    <label for="edit-member-search">Find sales executives</label>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-member-search" type="search" placeholder="Search by name or EMP ID" data-edit-member-search>
                </div>

                <div class="max-h-48 flex-col gap-1 overflow-y-auto rounded-md border border-pedago-line bg-white p-2" id="edit-members-container">
                    @foreach ($salesExecutives as $exec)
                        @php($isMemberOfThisTeam = $exec->team_id === $team->id)
                        @php($isMemberOfOtherTeam = $exec->team_id !== null && ! $isMemberOfThisTeam)
                        <label @class([
                            'flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs hover:bg-pedago-canvas/60',
                            'opacity-50 cursor-not-allowed' => $isMemberOfOtherTeam,
                        ]) data-edit-member-item data-name="{{ strtolower($exec->name . ' ' . ($exec->office_id ?? '')) }}">
                            <span class="flex items-center gap-2">
                                <input class="size-3.5 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="members[]" value="{{ $exec->id }}" @checked(in_array($exec->id, old('members', $team->members->pluck('id')->all()), false)) @disabled($isMemberOfOtherTeam) data-edit-member-checkbox>
                                <span class="font-medium text-pedago-ink">{{ $exec->name }}</span>
                                <span class="num text-[11px] text-pedago-muted">({{ $exec->office_id ?? '#'.$exec->id }})</span>
                            </span>
                            @if ($isMemberOfThisTeam)
                                <span class="text-[10px] font-semibold text-pedago-blue-dark">In this team</span>
                            @elseif ($isMemberOfOtherTeam)
                                <span class="text-[10px] font-semibold text-pedago-muted">In {{ $exec->team->name }}</span>
                            @else
                                <span class="text-[10px] font-semibold text-pedago-green">No team</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-pedago-muted">
                    <span data-edit-selected-member-count>{{ $team->members->count() }}</span> executives selected. Each executive belongs to one team. To move someone, remove them from their current team first.
                </p>
                <div class="rounded-md bg-pedago-amber-tint p-2.5 text-[11px] text-pedago-amber">
                    Removing a member does not move their open leads. Reassign them from All leads.
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-pedago-line pt-4">
                <div class="flex flex-col gap-0.5">
                    <button class="rounded-md border border-pedago-red-line px-3 py-1.5 text-xs font-semibold text-pedago-red transition hover:bg-pedago-red-tint" type="button" data-toggle-team-status>
                        {{ $team->is_active ? 'Deactivate team' : 'Activate team' }}
                    </button>
                    <span class="text-[10px] text-pedago-muted">
                        {{ $team->is_active ? "Stops new batches. Open leads ({$stats['open_leads']}) stay with their owners." : 'Enables this team to receive new lead batches.' }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
                    <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white hover:bg-pedago-blue-dark" type="submit">Save changes</button>
                </div>
            </div>
        </form>
    </dialog>

    {{-- Remove Member Confirmation Modal --}}
    <dialog id="member-remove-modal" class="m-auto w-[min(94vw,28rem)] rounded-lg border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-pedago-ink/45">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-sm font-bold text-pedago-ink">Remove member from team?</h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <div class="flex flex-col gap-3 px-5 py-4 text-xs leading-5 text-pedago-muted">
            <p>Are you sure you want to remove <strong class="text-pedago-ink" data-remove-member-name></strong> (<span class="num text-pedago-ink" data-remove-member-id></span>) from <strong>{{ $team->name }}</strong>?</p>
            <div class="rounded-md border border-pedago-amber/30 bg-pedago-amber-tint p-2.5 text-pedago-amber">
                Their open leads will remain with them. You can reassign leads from the All Leads screen.
            </div>
        </div>
        <form class="flex justify-end gap-2 border-t border-pedago-line px-5 py-3" id="member-remove-form" method="POST" action="">
            @csrf
            @method('DELETE')
            <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
            <button class="rounded-md bg-pedago-red px-4 py-2 text-sm font-semibold text-white hover:bg-pedago-red" type="submit">Remove member</button>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        const editDialog = document.getElementById('team-edit-modal');
        const removeDialog = document.getElementById('member-remove-modal');
        const removeForm = document.getElementById('member-remove-form');

        document.querySelectorAll('[data-open-edit]').forEach((btn) => {
            btn.addEventListener('click', () => editDialog.showModal());
        });

        document.querySelectorAll('[data-close-dialog]').forEach((btn) => {
            btn.addEventListener('click', () => btn.closest('dialog').close());
        });

        document.querySelectorAll('dialog').forEach((dlg) => {
            dlg.addEventListener('click', (e) => {
                if (e.target === dlg) dlg.close();
            });
        });

        const editSearch = document.querySelector('[data-edit-member-search]');
        if (editSearch) {
            editSearch.addEventListener('input', (e) => {
                const val = e.target.value.toLowerCase().trim();
                document.querySelectorAll('[data-edit-member-item]').forEach((item) => {
                    const name = item.dataset.name || '';
                    item.style.display = name.includes(val) ? 'flex' : 'none';
                });
            });
        }

        const updateSelectedCount = () => {
            const count = document.querySelectorAll('[data-edit-member-checkbox]:checked').length;
            const el = document.querySelector('[data-edit-selected-member-count]');
            if (el) el.textContent = count;
        };

        document.querySelectorAll('[data-edit-member-checkbox]').forEach((cb) => {
            cb.addEventListener('change', updateSelectedCount);
        });

        const toggleBtn = document.querySelector('[data-toggle-team-status]');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const current = document.getElementById('edit-is-active').value === '1';
                document.getElementById('edit-is-active').value = current ? '0' : '1';
                toggleBtn.closest('form').submit();
            });
        }

        // Setup member remove modal
        document.querySelectorAll('[data-remove-member]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const memberId = btn.dataset.id;
                const memberName = btn.dataset.name;
                const officeId = btn.dataset.officeId;

                removeForm.action = `{{ url('/teams/' . $team->id . '/members') }}/${memberId}`;
                document.querySelector('[data-remove-member-name]').textContent = memberName;
                document.querySelector('[data-remove-member-id]').textContent = officeId;

                removeDialog.showModal();
            });
        });
    </script>
@endpush
