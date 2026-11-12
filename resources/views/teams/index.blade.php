@extends('layouts.app', ['title' => 'Teams'])

@section('page-title', 'Teams')
@section('page-description', 'Manage operational sales squads, leadership, and member allocations.')
@section('page-actions')
    <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="button" data-open-dialog="team-create-modal">
        + Create team
    </button>
@endsection

@section('content')
    {{-- 1. KPI Summary Cards --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Team summary">
        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3">
            <p class="text-xs text-pedago-muted">Active teams</p>
            <p class="mt-1 text-2xl font-bold leading-none text-pedago-ink">{{ number_format($stats['active_teams']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">{{ $stats['inactive_teams'] }} inactive</p>
        </article>

        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3">
            <p class="text-xs text-pedago-muted">Sales executives</p>
            <p class="mt-1 text-2xl font-bold leading-none text-pedago-ink">{{ number_format($stats['executives_in_team']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">in a team</p>
        </article>

        <article @class([
            'rounded-lg border bg-white px-4 py-3',
            'border-rose-200' => $stats['executives_without_team'] > 0,
            'border-pedago-line' => $stats['executives_without_team'] === 0,
        ])>
            <p class="text-xs text-pedago-muted">Executives without a team</p>
            <p @class([
                'mt-1 text-2xl font-bold leading-none',
                'text-rose-700' => $stats['executives_without_team'] > 0,
                'text-pedago-ink' => $stats['executives_without_team'] === 0,
            ])>{{ number_format($stats['executives_without_team']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">They receive no leads</p>
        </article>

        <article class="rounded-lg border border-pedago-line bg-white px-4 py-3">
            <p class="text-xs text-pedago-muted">Available now</p>
            <p class="mt-1 text-2xl font-bold leading-none text-pedago-ink">{{ number_format($stats['available_now']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">of {{ number_format($stats['total_executives']) }} on duty</p>
        </article>

        <article @class([
            'rounded-lg border bg-white px-4 py-3',
            'border-rose-200' => $stats['overdue_followups'] > 0,
            'border-pedago-line' => $stats['overdue_followups'] === 0,
        ])>
            <p class="text-xs text-pedago-muted">Overdue follow-ups</p>
            <p @class([
                'mt-1 text-2xl font-bold leading-none',
                'text-rose-700' => $stats['overdue_followups'] > 0,
                'text-pedago-ink' => $stats['overdue_followups'] === 0,
            ])>{{ number_format($stats['overdue_followups']) }}</p>
            <p class="mt-1 text-[11px] text-pedago-muted">across all teams</p>
        </article>
    </section>

    {{-- 2. Filter section --}}
    <section class="flex flex-col gap-3 rounded-lg border border-pedago-line bg-white p-4" aria-labelledby="team-filter-heading">
        <h2 class="text-sm font-bold" id="team-filter-heading">Filter teams</h2>
        <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]" method="GET" action="{{ route('teams.index') }}">
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="q">
                Search
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="q" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Team, leader, supervisor or member name/ID">
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="fs">
                Supervisor
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="fs" name="supervisor">
                    <option value="">All supervisors</option>
                    @foreach ($supervisors as $sup)
                        <option value="{{ $sup->id }}" @selected((string) $filters['supervisor'] === (string) $sup->id)>
                            {{ $sup->name }} ({{ $sup->office_id ?? '#'.$sup->id }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-muted" for="fa">
                Status
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal text-pedago-ink focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="fa" name="status">
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="all" @selected($filters['status'] === 'all')>All</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
            </label>

            <div class="flex items-end gap-2">
                <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">
                    Filter
                </button>
                <a class="inline-flex items-center justify-center rounded-md border border-pedago-blue px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('teams.index') }}">
                    Clear
                </a>
            </div>
        </form>
    </section>

    {{-- 3. Main Grid: Teams Table + Side Comparison Card --}}
    <div class="grid gap-5 xl:grid-cols-[1fr_320px]">
        {{-- Left: Teams Table --}}
        <section class="overflow-hidden rounded-lg border border-pedago-line bg-white" aria-labelledby="teams-table-heading">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-pedago-line px-4 py-3">
                <h2 class="text-sm font-bold" id="teams-table-heading">Teams</h2>
                <span class="text-xs text-pedago-muted">Showing {{ $teams->firstItem() ?? 0 }}–{{ $teams->lastItem() ?? 0 }} of {{ number_format($teams->total()) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[950px] border-collapse text-left">
                    <thead class="border-b border-pedago-line bg-pedago-canvas/40 text-[10px] uppercase tracking-wide text-pedago-muted">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Team</th>
                            <th class="px-3 py-3 font-semibold">Leader</th>
                            <th class="px-3 py-3 font-semibold">Supervisor</th>
                            <th class="px-3 py-3 font-semibold">Members</th>
                            <th class="px-3 py-3 font-semibold">Available now</th>
                            <th class="px-3 py-3 font-semibold">Open leads</th>
                            <th class="px-3 py-3 font-semibold">Conversion (30d)</th>
                            <th class="px-3 py-3 font-semibold">Overdue</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pedago-line text-xs">
                        @forelse ($teams as $team)
                            @php($tStat = $teamStats[$team->id] ?? [])
                            <tr class="transition-colors hover:bg-pedago-canvas/60">
                                <td class="px-4 py-3 font-bold text-pedago-ink">
                                    <a class="hover:text-pedago-blue hover:underline" href="{{ route('teams.show', $team) }}">
                                        {{ $team->name }}
                                    </a>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($team->teamLeader)
                                        <span class="font-medium text-pedago-ink">{{ $team->teamLeader->name }}</span>
                                        <span class="font-mono text-[11px] text-pedago-muted">({{ $team->teamLeader->office_id ?? '#'.$team->teamLeader->id }})</span>
                                    @else
                                        <span class="text-pedago-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-pedago-muted">
                                    @forelse ($team->supervisors as $sup)
                                        <span>{{ $sup->name }} <span class="font-mono text-[10px] text-pedago-muted">({{ $sup->office_id ?? '#'.$sup->id }})</span></span>@if (! $loop->last), @endif
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>
                                <td class="px-3 py-3 font-mono">{{ $tStat['members_count'] ?? 0 }}</td>
                                <td class="px-3 py-3 font-mono">{{ $tStat['available_count'] ?? 0 }}</td>
                                <td class="px-3 py-3 font-mono">{{ number_format($tStat['open_leads_count'] ?? 0) }}</td>
                                <td class="px-3 py-3 font-mono">{{ $tStat['conversion_rate_30d'] ?? 0 }}%</td>
                                <td class="px-3 py-3 font-mono">
                                    @if (($tStat['overdue_count'] ?? 0) > 0)
                                        <span class="font-semibold text-rose-700">{{ $tStat['overdue_count'] }}</span>
                                    @else
                                        <span>0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $team->is_active,
                                        'bg-slate-100 text-slate-700' => ! $team->is_active,
                                    ])>
                                        <span aria-hidden="true">●</span> {{ $team->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        {{-- View action icon --}}
                                        <a class="inline-flex size-7 items-center justify-center rounded-md border border-pedago-line text-pedago-ink transition hover:border-pedago-blue hover:bg-pedago-tint hover:text-pedago-blue-dark" href="{{ route('teams.show', $team) }}" title="View team" aria-label="View {{ $team->name }}">
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                                                <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </a>

                                        {{-- Edit action icon --}}
                                        <button class="inline-flex size-7 items-center justify-center rounded-md border border-pedago-blue text-pedago-blue-dark transition hover:bg-pedago-tint" type="button" title="Edit team" aria-label="Edit {{ $team->name }}"
                                            data-edit-team
                                            data-id="{{ $team->id }}"
                                            data-name="{{ $team->name }}"
                                            data-leader-id="{{ $team->team_leader_id }}"
                                            data-supervisors='@json($team->supervisors->pluck("id"))'
                                            data-members='@json($team->members->pluck("id"))'
                                            data-active="{{ $team->is_active ? '1' : '0' }}"
                                            data-open-leads="{{ $tStat['open_leads_count'] ?? 0 }}">
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                                <path d="M3.5 5.75c0-.414.336-.75.75-.75H10a.75.75 0 010 1.5H4.25v9h9V10a.75.75 0 011.5 0v5.75c0 .966-.784 1.75-1.75 1.75h-9A1.75 1.75 0 012.5 15.75v-9c0-.966.784-1.75 1.75-1.75z" />
                                            </svg>
                                        </button>

                                        {{-- Delete action icon --}}
                                        <button class="inline-flex size-7 items-center justify-center rounded-md border border-rose-200 text-rose-700 transition hover:bg-rose-50" type="button" title="Delete team" aria-label="Delete {{ $team->name }}"
                                            data-delete-team
                                            data-id="{{ $team->id }}"
                                            data-name="{{ $team->name }}"
                                            data-leads="{{ $tStat['open_leads_count'] ?? 0 }}"
                                            data-members="{{ $tStat['members_count'] ?? 0 }}">
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-10 text-center text-sm text-pedago-muted" colspan="10">No teams match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-pedago-line px-4 py-3 text-xs text-pedago-muted">
                <span>Teams are deactivated, not deleted. Their leads and history stay.</span>
                @if ($teams->hasPages())
                    {{ $teams->links() }}
                @else
                    <span>Rows per page 25</span>
                @endif
            </div>
        </section>

        {{-- Right: Conversion by team comparison (Last 30 days) --}}
        <aside class="flex flex-col gap-4">
            <section class="rounded-lg border border-pedago-line bg-white p-4 shadow-sm" aria-labelledby="conversion-heading">
                <div class="flex items-center justify-between border-b border-pedago-line pb-3">
                    <h2 class="text-sm font-bold text-pedago-ink" id="conversion-heading">Conversion by team</h2>
                    <span class="text-[11px] text-pedago-muted">Last 30 days</span>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($conversionChart as $chartItem)
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-pedago-ink">{{ $chartItem['name'] }}</span>
                                <span class="font-mono font-bold text-pedago-ink">{{ $chartItem['rate'] }}%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-pedago-canvas">
                                <div class="h-full rounded-full bg-pedago-blue transition-all" style="width: {{ $chartItem['width_percent'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-pedago-muted">No team conversions in the last 30 days.</p>
                    @endforelse
                </div>
                <p class="mt-4 border-t border-pedago-line pt-3 text-[11px] text-pedago-muted">
                    Share of leads converted in the last 30 days.
                </p>
            </section>
        </aside>
    </div>

    {{-- 4. Create Team Modal --}}
    <dialog id="team-create-modal" class="m-auto max-h-[92vh] w-[min(94vw,34rem)] overflow-y-auto rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-base font-bold">Create team</h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <form class="flex flex-col gap-5 p-5" method="POST" action="{{ route('teams.store') }}">
            @csrf
            {{-- Team section --}}
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Team</span>
                <label class="mt-2 flex flex-col gap-1.5 text-xs font-semibold" for="create-team-name">
                    Team name <span class="text-pedago-red">*</span>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-team-name" maxlength="100" name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Alpha" required>
                </label>
                @error('name')<span class="mt-1 text-xs text-pedago-red">{{ $message }}</span>@enderror
            </div>

            {{-- Leadership section --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Leadership</span>
                <label class="flex flex-col gap-1.5 text-xs font-semibold" for="create-team-leader">
                    Team leader <span class="text-pedago-red">*</span>
                    <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-team-leader" name="team_leader_id" required>
                        <option value="">Select a leader</option>
                        @foreach ($potentialLeaders as $ldr)
                            @php($alreadyLeads = $ldr->teamsLed->first())
                            <option value="{{ $ldr->id }}" @selected((string) old('team_leader_id') === (string) $ldr->id) @disabled($alreadyLeads !== null)>
                                {{ $ldr->name }} ({{ $ldr->office_id ?? '#'.$ldr->id }}) - {{ $ldr->role }}
                                @if ($alreadyLeads)
                                    — Already leads {{ $alreadyLeads->name }} (Unavailable)
                                @endif
                            </option>
                        @endforeach
                    </select>
                </label>
                @error('team_leader_id')<span class="text-xs text-pedago-red">{{ $message }}</span>@enderror
                <p class="text-[11px] text-pedago-muted">A leader can only be assigned to one team. Leaders already heading a team are disabled.</p>

                <fieldset class="mt-1 flex flex-col gap-2">
                    <legend class="text-xs font-semibold">Supervisors</legend>
                    <div class="flex flex-col gap-1.5 rounded-md border border-pedago-line bg-pedago-canvas/30 p-2.5">
                        @foreach ($supervisors as $sup)
                            <label class="flex items-center gap-2 text-xs">
                                <input class="size-4 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="supervisors[]" value="{{ $sup->id }}" @checked(in_array($sup->id, old('supervisors', []), false))>
                                <span class="font-medium text-pedago-ink">{{ $sup->name }} <span class="font-mono text-[11px] text-pedago-muted">({{ $sup->office_id ?? '#'.$sup->id }})</span></span>
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
                    <label for="create-member-search">Find sales executives</label>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-member-search" type="search" placeholder="Search by name or EMP ID" data-member-search>
                </div>

                <div class="max-h-48 flex-col gap-1 overflow-y-auto rounded-md border border-pedago-line bg-white p-2" data-member-list>
                    @foreach ($salesExecutives as $exec)
                        @php($isInOtherTeam = $exec->team_id !== null)
                        <label @class([
                            'flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs hover:bg-pedago-canvas/60',
                            'opacity-50 cursor-not-allowed' => $isInOtherTeam,
                        ]) data-member-item data-name="{{ strtolower($exec->name . ' ' . ($exec->office_id ?? '')) }}">
                            <span class="flex items-center gap-2">
                                <input class="size-3.5 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="members[]" value="{{ $exec->id }}" @disabled($isInOtherTeam) @checked(in_array($exec->id, old('members', []), false)) data-member-checkbox>
                                <span class="font-medium text-pedago-ink">{{ $exec->name }}</span>
                                <span class="font-mono text-[11px] text-pedago-muted">({{ $exec->office_id ?? '#'.$exec->id }})</span>
                            </span>
                            @if ($isInOtherTeam)
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">In {{ $exec->team->name }}</span>
                            @else
                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">No team</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-pedago-muted">
                    <span data-selected-member-count>0</span> executives selected. Each executive belongs to one team. To move someone, remove them from their current team first.
                </p>
            </div>

            <div class="flex justify-end gap-2 border-t border-pedago-line pt-4">
                <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
                <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white hover:bg-pedago-blue-dark" type="submit">Create team</button>
            </div>
        </form>
    </dialog>

    {{-- 5. Edit Team Modal --}}
    <dialog id="team-edit-modal" class="m-auto max-h-[92vh] w-[min(94vw,34rem)] overflow-y-auto rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-base font-bold">Edit team <span data-edit-team-title></span></h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <form class="flex flex-col gap-5 p-5" id="team-edit-form" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_active" id="edit-is-active" value="1">

            {{-- Team section --}}
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Team</span>
                <label class="mt-2 flex flex-col gap-1.5 text-xs font-semibold" for="edit-team-name">
                    Team name <span class="text-pedago-red">*</span>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-team-name" maxlength="100" name="name" type="text" required>
                </label>
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
                            <option value="{{ $ldr->id }}" data-leads-team-id="{{ $otherTeam?->id ?? '' }}" data-leads-team-name="{{ $otherTeam?->name ?? '' }}">
                                {{ $ldr->name }} ({{ $ldr->office_id ?? '#'.$ldr->id }}) - {{ $ldr->role }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <p class="text-[11px] text-pedago-muted">A leader can only lead one team at a time.</p>

                <fieldset class="mt-1 flex flex-col gap-2">
                    <legend class="text-xs font-semibold">Supervisors</legend>
                    <div class="flex flex-col gap-1.5 rounded-md border border-pedago-line bg-pedago-canvas/30 p-2.5" id="edit-supervisors-container">
                        @foreach ($supervisors as $sup)
                            <label class="flex items-center gap-2 text-xs">
                                <input class="size-4 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="supervisors[]" value="{{ $sup->id }}" data-edit-supervisor-checkbox="{{ $sup->id }}">
                                <span class="font-medium text-pedago-ink">{{ $sup->name }} <span class="font-mono text-[11px] text-pedago-muted">({{ $sup->office_id ?? '#'.$sup->id }})</span></span>
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
                        <label class="flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs hover:bg-pedago-canvas/60" data-edit-member-item data-name="{{ strtolower($exec->name . ' ' . ($exec->office_id ?? '')) }}" data-member-id="{{ $exec->id }}" data-current-team-id="{{ $exec->team_id }}">
                            <span class="flex items-center gap-2">
                                <input class="size-3.5 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="members[]" value="{{ $exec->id }}" data-edit-member-checkbox="{{ $exec->id }}">
                                <span class="font-medium text-pedago-ink">{{ $exec->name }}</span>
                                <span class="font-mono text-[11px] text-pedago-muted">({{ $exec->office_id ?? '#'.$exec->id }})</span>
                            </span>
                            <span class="text-[10px] font-semibold text-pedago-muted" data-edit-member-badge>
                                {{ $exec->team ? "In {$exec->team->name}" : 'No team' }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-pedago-muted">
                    <span data-edit-selected-member-count>0</span> executives selected. Each executive belongs to one team. To move someone, remove them from their current team first.
                </p>
                <div class="rounded-md bg-amber-50 p-2.5 text-[11px] text-amber-800">
                    Removing a member does not move their open leads. Reassign them from All leads.
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-pedago-line pt-4">
                <div class="flex flex-col gap-0.5">
                    <button class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50" type="button" data-toggle-team-status>
                        Deactivate team
                    </button>
                    <span class="text-[10px] text-pedago-muted" data-status-note>Stops new batches. Open leads stay with their owners.</span>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
                    <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white hover:bg-pedago-blue-dark" type="submit">Save changes</button>
                </div>
            </div>
        </form>
    </dialog>

    {{-- 6. Delete Team Confirmation Modal --}}
    <dialog id="team-delete-modal" class="m-auto w-[min(94vw,28rem)] rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <h2 class="text-sm font-bold text-pedago-ink">Delete team?</h2>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <div class="flex flex-col gap-3 px-5 py-4 text-xs leading-5 text-pedago-muted">
            <p>You are about to delete <strong class="text-pedago-ink" data-delete-team-name></strong>.</p>
            <div id="delete-has-leads-warning" class="hidden rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800">
                <p class="font-semibold">⚠️ Cannot delete team with active leads.</p>
                <p class="mt-1">This team has <span class="font-bold" data-delete-leads-count></span> lead(s) attached. Deactivate the team instead, or reassign leads before deletion.</p>
            </div>
            <div id="delete-safe-note" class="rounded-md border border-rose-100 bg-rose-50/50 p-3 text-rose-800">
                <p>This team has 0 leads. Deleting it will release <span class="font-bold" data-delete-members-count>0</span> member(s) to 'No team' and clear supervisor assignments.</p>
                <p class="mt-1 font-semibold">This action cannot be undone.</p>
            </div>
        </div>
        <form class="flex justify-end gap-2 border-t border-pedago-line px-5 py-3" id="team-delete-form" method="POST" action="">
            @csrf
            @method('DELETE')
            <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
            <button class="rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-40" id="confirm-delete-btn" type="submit">
                Delete team
            </button>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        const teamDialogs = {
            create: document.getElementById('team-create-modal'),
            edit: document.getElementById('team-edit-modal'),
            delete: document.getElementById('team-delete-modal'),
        };
        const editForm = document.getElementById('team-edit-form');
        const deleteForm = document.getElementById('team-delete-form');

        document.querySelectorAll('[data-open-dialog]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const dlgId = btn.getAttribute('data-open-dialog');
                if (dlgId === 'team-create-modal') {
                    teamDialogs.create.showModal();
                }
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach((btn) => {
            btn.addEventListener('click', () => btn.closest('dialog').close());
        });

        document.querySelectorAll('dialog').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        });

        // Filter members in create modal
        const createSearchInput = document.querySelector('[data-member-search]');
        if (createSearchInput) {
            createSearchInput.addEventListener('input', (e) => {
                const val = e.target.value.toLowerCase().trim();
                document.querySelectorAll('[data-member-item]').forEach((item) => {
                    const name = item.dataset.name || '';
                    item.style.display = name.includes(val) ? 'flex' : 'none';
                });
            });
        }

        const updateCreateMemberCount = () => {
            const checkedCount = document.querySelectorAll('[data-member-checkbox]:checked').length;
            const el = document.querySelector('[data-selected-member-count]');
            if (el) el.textContent = checkedCount;
        };
        document.querySelectorAll('[data-member-checkbox]').forEach((cb) => {
            cb.addEventListener('change', updateCreateMemberCount);
        });
        updateCreateMemberCount();

        // Edit team modal setup
        const editSearchInput = document.querySelector('[data-edit-member-search]');
        if (editSearchInput) {
            editSearchInput.addEventListener('input', (e) => {
                const val = e.target.value.toLowerCase().trim();
                document.querySelectorAll('[data-edit-member-item]').forEach((item) => {
                    const name = item.dataset.name || '';
                    item.style.display = name.includes(val) ? 'flex' : 'none';
                });
            });
        }

        const updateEditMemberCount = () => {
            const checkedCount = document.querySelectorAll('#edit-members-container input[type="checkbox"]:checked').length;
            const el = document.querySelector('[data-edit-selected-member-count]');
            if (el) el.textContent = checkedCount;
        };

        document.querySelectorAll('[data-edit-team]').forEach((button) => {
            button.addEventListener('click', () => {
                const data = button.dataset;
                const teamId = data.id;
                const teamName = data.name;
                const leaderId = data.leaderId;
                const supervisors = JSON.parse(data.supervisors || '[]');
                const members = JSON.parse(data.members || '[]');
                const isActive = data.active === '1';
                const openLeads = data.openLeads;

                editForm.action = `{{ url('/teams') }}/${teamId}`;
                document.querySelector('[data-edit-team-title]').textContent = teamName;
                document.getElementById('edit-team-name').value = teamName;
                document.getElementById('edit-is-active').value = isActive ? '1' : '0';

                // Team leader options: disable any leader leading another team
                const leaderSelect = document.getElementById('edit-team-leader');
                Array.from(leaderSelect.options).forEach((opt) => {
                    const leadsTeamId = opt.dataset.leadsTeamId;
                    const leadsTeamName = opt.dataset.leadsTeamName;
                    if (leadsTeamId && leadsTeamId !== String(teamId)) {
                        opt.disabled = true;
                        if (!opt.text.includes('(Unavailable)')) {
                            opt.text = opt.text + ` — Already leads ${leadsTeamName} (Unavailable)`;
                        }
                    } else {
                        opt.disabled = false;
                    }
                });
                leaderSelect.value = leaderId;

                // Supervisors
                document.querySelectorAll('[data-edit-supervisor-checkbox]').forEach((cb) => {
                    cb.checked = supervisors.includes(Number(cb.value));
                });

                // Members
                document.querySelectorAll('[data-edit-member-item]').forEach((item) => {
                    const memberId = Number(item.dataset.memberId);
                    const currentTeamId = item.dataset.currentTeamId;
                    const cb = item.querySelector('input[type="checkbox"]');
                    const badge = item.querySelector('[data-edit-member-badge]');

                    const isMemberOfThisTeam = members.includes(memberId);
                    const isMemberOfOtherTeam = currentTeamId && currentTeamId !== String(teamId);

                    cb.checked = isMemberOfThisTeam;
                    cb.disabled = isMemberOfOtherTeam;
                    item.classList.toggle('opacity-50', Boolean(isMemberOfOtherTeam));
                    item.classList.toggle('cursor-not-allowed', Boolean(isMemberOfOtherTeam));

                    if (isMemberOfThisTeam) {
                        badge.textContent = 'In this team';
                        badge.className = 'text-[10px] font-semibold text-pedago-blue-dark';
                    } else if (isMemberOfOtherTeam) {
                        badge.textContent = 'In another team';
                        badge.className = 'text-[10px] font-semibold text-slate-500';
                    } else {
                        badge.textContent = 'No team';
                        badge.className = 'text-[10px] font-semibold text-emerald-700';
                    }
                });

                // Status toggle button
                const toggleBtn = document.querySelector('[data-toggle-team-status]');
                const statusNote = document.querySelector('[data-status-note]');
                if (isActive) {
                    toggleBtn.textContent = 'Deactivate team';
                    toggleBtn.className = 'rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50';
                    statusNote.textContent = `Stops new batches. Open leads (${openLeads}) stay with their owners.`;
                } else {
                    toggleBtn.textContent = 'Activate team';
                    toggleBtn.className = 'rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50';
                    statusNote.textContent = 'Enables this team to receive new lead batches.';
                }

                toggleBtn.onclick = () => {
                    const currentActive = document.getElementById('edit-is-active').value === '1';
                    document.getElementById('edit-is-active').value = currentActive ? '0' : '1';
                    editForm.submit();
                };

                updateEditMemberCount();
                teamDialogs.edit.showModal();
            });
        });

        document.querySelectorAll('#edit-members-container input[type="checkbox"]').forEach((cb) => {
            cb.addEventListener('change', updateEditMemberCount);
        });

        // Delete team modal setup
        document.querySelectorAll('[data-delete-team]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const teamId = btn.dataset.id;
                const teamName = btn.dataset.name;
                const leadsCount = Number(btn.dataset.leads || 0);
                const membersCount = Number(btn.dataset.members || 0);

                deleteForm.action = `{{ url('/teams') }}/${teamId}`;
                document.querySelector('[data-delete-team-name]').textContent = teamName;
                document.querySelector('[data-delete-leads-count]').textContent = leadsCount;
                document.querySelector('[data-delete-members-count]').textContent = membersCount;

                const warningEl = document.getElementById('delete-has-leads-warning');
                const safeEl = document.getElementById('delete-safe-note');
                const confirmBtn = document.getElementById('confirm-delete-btn');

                if (leadsCount > 0) {
                    warningEl.classList.remove('hidden');
                    safeEl.classList.add('hidden');
                    confirmBtn.disabled = true;
                    confirmBtn.title = 'Teams with leads cannot be deleted. Deactivate instead.';
                } else {
                    warningEl.classList.add('hidden');
                    safeEl.classList.remove('hidden');
                    confirmBtn.disabled = false;
                    confirmBtn.title = 'Delete team';
                }

                teamDialogs.delete.showModal();
            });
        });
    </script>
@endpush
