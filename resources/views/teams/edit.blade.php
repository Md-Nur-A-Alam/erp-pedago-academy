@extends('layouts.app', ['title' => 'Edit team ' . $team->name])

@section('page-title', 'Edit team ' . $team->name)
@section('page-description', 'Update team name, leader, supervisors, member allocation, or active status.')
@section('page-actions')
    <div class="flex items-center gap-2">
        <a class="inline-flex items-center justify-center gap-1.5 rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('teams.show', $team) }}">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
            <span>View team</span>
        </a>
        <a class="inline-flex items-center justify-center gap-1.5 rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-muted transition hover:bg-pedago-canvas" href="{{ route('teams.index') }}">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            <span>All teams</span>
        </a>
    </div>
@endsection

@section('content')
    <section class="mx-auto w-full max-w-3xl rounded-lg border border-pedago-line bg-white" aria-labelledby="edit-team-heading">
        <div class="border-b border-pedago-line px-6 py-4">
            <h2 class="text-base font-bold text-pedago-ink" id="edit-team-heading">Edit team {{ $team->name }}</h2>
        </div>

        <form class="flex flex-col gap-5 p-6" id="team-edit-page-form" method="POST" action="{{ route('teams.update', $team) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_active" id="edit-page-is-active" value="{{ old('is_active', $team->is_active ? '1' : '0') }}">

            {{-- 1. Team details --}}
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Team</span>
                <label class="mt-2 flex flex-col gap-1.5 text-xs font-semibold" for="edit-team-name">
                    Team name <span class="text-pedago-red">*</span>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-team-name" maxlength="100" name="name" type="text" value="{{ old('name', $team->name) }}" required>
                </label>
                @error('name')<span class="mt-1 text-xs text-pedago-red">{{ $message }}</span>@enderror
            </div>

            {{-- 2. Primary Duty Time (Shift) --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Primary Duty Time (Shift)</span>
                    <span class="rounded bg-pedago-tint px-2 py-0.5 text-[11px] font-semibold text-pedago-blue-dark">Inherited by members</span>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-shift-id">
                            Predefined shift
                            <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-shift-id" name="shift_id">
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
                        <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-shift-start">
                            Or custom start
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-shift-start" name="shift_start_time" type="time" value="{{ old('shift_start_time') }}">
                        </label>
                    </div>
                    <div>
                        <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-shift-end">
                            Custom end
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-shift-end" name="shift_end_time" type="time" value="{{ old('shift_end_time') }}">
                        </label>
                    </div>
                </div>
                @if ($team->shift)
                    <p class="text-[11px] text-pedago-green">Current primary duty time: <strong>{{ \Carbon\Carbon::parse($team->shift->shift_start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($team->shift->shift_end_time)->format('h:i A') }}</strong> ({{ $team->shift->working_hours }} hrs).</p>
                @endif
                <p class="text-[11px] text-pedago-muted">All team members will primarily inherit this duty time. Specific dates can be overridden from employee details calendar.</p>
            </div>

            {{-- 2. Leadership --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Leadership</span>
                <label class="flex flex-col gap-1.5 text-xs font-semibold" for="edit-team-leader">
                    Team leader <span class="text-pedago-red">*</span>
                    <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-team-leader" name="team_leader_id" required>
                        <option value="">Select a leader</option>
                        @foreach ($potentialLeaders as $ldr)
                            @php($isCurrentTeamLeader = (int)$team->team_leader_id === (int)$ldr->id)
                            @php($leadsOtherTeam = $ldr->teamsLed->isNotEmpty() && ! $isCurrentTeamLeader)
                            @php($otherTeamName = $ldr->teamsLed->first()?->name)
                            <option value="{{ $ldr->id }}"
                                @selected((string) old('team_leader_id', $team->team_leader_id) === (string) $ldr->id)
                                @disabled($leadsOtherTeam)>
                                {{ $ldr->name }} ({{ $ldr->office_id ?? '#'.$ldr->id }}) · {{ $ldr->role }}
                                @if ($leadsOtherTeam)
                                    — Already leads {{ $otherTeamName }} (Unavailable)
                                @endif
                            </option>
                        @endforeach
                    </select>
                </label>
                @error('team_leader_id')<span class="text-xs text-pedago-red">{{ $message }}</span>@enderror
                <p class="text-[11px] text-pedago-muted">A leader can only head one team. Leaders heading other teams cannot be selected.</p>

                <fieldset class="mt-1 flex flex-col gap-2">
                    <legend class="text-xs font-semibold">Supervisors</legend>
                    <div class="flex flex-col gap-1.5 rounded-md border border-pedago-line bg-pedago-canvas/30 p-2.5">
                        @foreach ($supervisors as $sup)
                            <label class="flex items-center gap-2 text-xs">
                                <input class="size-4 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="supervisors[]" value="{{ $sup->id }}" @checked(in_array($sup->id, old('supervisors', $team->supervisors->pluck('id')->all()), false))>
                                <span class="font-medium text-pedago-ink">{{ $sup->name }} <span class="num text-xs text-pedago-muted">({{ $sup->office_id ?? '#'.$sup->id }})</span></span>
                                <small class="text-pedago-muted">Supervises {{ $sup->supervisedTeams->pluck('name')->join(', ') ?: 'no teams' }}</small>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-pedago-muted">A supervisor can oversee several teams.</p>
                </fieldset>
            </div>

            {{-- 3. Members --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Members</span>
                <div class="flex flex-col gap-1.5 text-xs font-semibold">
                    <label for="edit-member-search">Find sales executives</label>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="edit-member-search" type="search" placeholder="Search by name or ID" data-edit-member-search>
                </div>

                <div class="max-h-60 flex-col gap-1 overflow-y-auto rounded-md border border-pedago-line bg-white p-2" id="edit-members-container">
                    @foreach ($salesExecutives as $exec)
                        @php($isMemberOfThisTeam = $exec->team_id === $team->id)
                        @php($isMemberOfOtherTeam = $exec->team_id !== null && ! $isMemberOfThisTeam)
                        <label @class([
                            'flex items-center justify-between gap-2 rounded px-2.5 py-2 text-xs hover:bg-pedago-canvas/60',
                            'opacity-50 cursor-not-allowed' => $isMemberOfOtherTeam,
                        ]) data-edit-member-item data-name="{{ strtolower($exec->name . ' ' . ($exec->office_id ?? '') . ' #' . $exec->id) }}">
                            <span class="flex items-center gap-2">
                                <input class="size-3.5 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue" type="checkbox" name="members[]" value="{{ $exec->id }}" @checked(in_array($exec->id, old('members', $team->members->pluck('id')->all()), false)) @disabled($isMemberOfOtherTeam) data-edit-member-checkbox>
                                <span class="font-medium text-pedago-ink">{{ $exec->name }} <span class="num text-[11px] text-pedago-muted">({{ $exec->office_id ?? '#'.$exec->id }})</span></span>
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
                <div class="rounded-md bg-pedago-amber-tint p-3 text-[11px] text-pedago-amber">
                    Removing a member does not move their open leads. Reassign them from All leads.
                </div>
            </div>

            {{-- 4. Status toggle and submit buttons --}}
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-pedago-line pt-5">
                <div class="flex flex-col gap-0.5">
                    <button class="rounded-md border border-pedago-red-line px-3 py-1.5 text-xs font-semibold text-pedago-red transition hover:bg-pedago-red-tint" type="button" data-toggle-team-status>
                        {{ $team->is_active ? 'Deactivate team' : 'Activate team' }}
                    </button>
                    <span class="text-[10px] text-pedago-muted">
                        {{ $team->is_active ? "Stops new batches. Open leads ({$openLeadsCount}) stay with their owners." : 'Enables this team to receive new lead batches.' }}
                    </span>
                </div>

                <div class="flex gap-2">
                    <a class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink transition hover:bg-pedago-canvas" href="{{ route('teams.show', $team) }}">
                        Cancel
                    </a>
                    <button class="rounded-md bg-pedago-blue px-5 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">
                        Save changes
                    </button>
                </div>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
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
                const current = document.getElementById('edit-page-is-active').value === '1';
                document.getElementById('edit-page-is-active').value = current ? '0' : '1';
                toggleBtn.closest('form').submit();
            });
        }
    </script>
@endpush

