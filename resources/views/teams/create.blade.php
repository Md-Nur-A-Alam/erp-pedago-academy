@extends('layouts.app', ['title' => 'Create team'])

@section('page-title', 'Create team')
@section('page-description', 'Set up an operational sales squad with a leader, supervisors, and executive members.')
@section('page-actions')
    <a class="inline-flex items-center justify-center rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('teams.index') }}">
        ← Back to teams
    </a>
@endsection

@section('content')
    <section class="mx-auto w-full max-w-3xl rounded-xl border border-pedago-line bg-white shadow-sm" aria-labelledby="create-team-heading">
        <div class="border-b border-pedago-line px-6 py-4">
            <h2 class="text-base font-bold text-pedago-ink" id="create-team-heading">Create team</h2>
        </div>

        <form class="flex flex-col gap-5 p-6" method="POST" action="{{ route('teams.store') }}">
            @csrf

            {{-- 1. Team details --}}
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Team</span>
                <label class="mt-2 flex flex-col gap-1.5 text-xs font-semibold" for="create-team-name">
                    Team name <span class="text-pedago-red">*</span>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-team-name" maxlength="100" name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Alpha" required>
                </label>
                @error('name')<span class="mt-1 text-xs text-pedago-red">{{ $message }}</span>@enderror
            </div>

            {{-- 2. Leadership --}}
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

            {{-- 3. Members --}}
            <div class="flex flex-col gap-3 border-t border-pedago-line pt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Members</span>
                <div class="flex flex-col gap-1.5 text-xs font-semibold">
                    <label for="create-member-search">Find sales executives</label>
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="create-member-search" type="search" placeholder="Search by name or EMP ID" data-member-search>
                </div>

                <div class="max-h-60 flex-col gap-1 overflow-y-auto rounded-md border border-pedago-line bg-white p-2" data-member-list>
                    @foreach ($salesExecutives as $exec)
                        @php($isInOtherTeam = $exec->team_id !== null)
                        <label @class([
                            'flex items-center justify-between gap-2 rounded px-2.5 py-2 text-xs hover:bg-pedago-canvas/60',
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

            <div class="flex items-center justify-end gap-2 border-t border-pedago-line pt-5">
                <a class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink transition hover:bg-pedago-canvas" href="{{ route('teams.index') }}">
                    Cancel
                </a>
                <button class="rounded-md bg-pedago-blue px-5 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">
                    Create team
                </button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
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
    </script>
@endpush
