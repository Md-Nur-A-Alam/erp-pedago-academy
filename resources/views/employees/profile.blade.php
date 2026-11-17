@extends('layouts.app', ['title' => 'My employee profile'])

@section('page-title', $isAdminEdit ? 'Edit employee profile' : 'My profile')
@section('page-description', 'Manage your personal information and employment details.')
@section('page-actions')
    <span @class([
        'rounded-full px-3 py-1.5 text-xs font-semibold',
        'bg-pedago-green-tint text-pedago-green' => $isProfileComplete,
        'bg-pedago-amber-tint text-pedago-amber' => ! $isProfileComplete,
    ])>Profile {{ $isProfileComplete ? 'complete' : 'incomplete' }}</span>
    @if ($pendingRequest)
        <span class="hidden rounded-full bg-pedago-amber-tint px-3 py-1.5 text-xs font-semibold text-pedago-amber sm:inline-flex">Approval pending</span>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="rounded-lg border border-pedago-green/30 bg-pedago-green-tint p-4 text-sm font-medium text-pedago-green">
            {{ session('status') }}
        </div>
    @endif

    @if ($pendingRequest)
        <section class="rounded-lg border border-pedago-amber/20 bg-pedago-amber-tint p-5">
            <h2 class="font-semibold text-pedago-amber">Pending employment changes</h2>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                @foreach ($pendingRequest->requested_changes as $field => $value)
                    <div class="flex flex-col gap-1">
                        <dt class="font-medium text-pedago-amber">{{ str($field)->replace('_', ' ')->title() }}</dt>
                        <dd class="text-pedago-ink">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($pendingRequest->reason)
                <p class="mt-3 text-sm text-pedago-ink">Reason: {{ $pendingRequest->reason }}</p>
            @endif
        </section>
    @endif

    <form class="flex flex-col gap-6" method="POST" action="{{ $isAdminEdit ? route('employees.update', $employee) : route('profile.update') }}">
        @csrf
        @method('PUT')

        <section class="rounded-lg border border-pedago-line bg-white p-6">
            <div class="mb-5 flex flex-col gap-2">
                <h2 class="text-lg font-semibold">Employment information</h2>
                <p class="text-sm text-pedago-muted">Changes to these fields require review by a higher role. Admin changes take effect immediately.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-2 text-sm font-medium">Full name
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="name" value="{{ old('name', $employee->name) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Office ID
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="office_id" value="{{ old('office_id', $employee->office_id) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Designation
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="designation" value="{{ old('designation', $employee->designation) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Joining date
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="joining_date" type="date" value="{{ old('joining_date', $employee->joining_date?->format('Y-m-d')) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Login phone number
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="phone_number" type="tel" value="{{ old('phone_number', $employee->user?->phone_number) }}" required>
                </label>
                @if ($isAdminEdit || $employee->isAdmin())
                    <label class="flex flex-col gap-2 text-sm font-medium">Role
                        <select class="rounded-md border border-pedago-line bg-white px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="role" required>
                            @foreach (\App\Models\Employee::ROLES as $role)
                                <option value="{{ $role }}" @selected(old('role', $employee->role) === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-2 text-sm font-medium">Employment status
                        <select class="rounded-md border border-pedago-line bg-white px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="status" required>
                            @foreach (['Active', 'Inactive', 'Suspended'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $employee->status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <p class="self-center text-sm text-pedago-muted">Current role: {{ $employee->role }} · {{ $employee->status }}</p>
                @endif
            </div>
            <label class="mt-4 flex flex-col gap-2 text-sm font-medium">Reason for employment changes
                <textarea class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="reason" rows="3">{{ old('reason') }}</textarea>
            </label>
        </section>

        <section class="rounded-lg border border-pedago-line bg-white p-6">
            <div class="mb-5 flex flex-col gap-2">
                <h2 class="text-lg font-semibold">Personal details</h2>
                <p class="text-sm text-pedago-muted">Update these profile details directly. Fields marked required are needed to finish onboarding.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-2 text-sm font-medium">Personal email
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="email" type="email" value="{{ old('email', $employee->details?->email) }}">
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Date of birth <span class="text-pedago-red">Required</span>
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="dob" type="date" value="{{ old('dob', $employee->details?->dob?->format('Y-m-d')) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Secondary contact number
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="secondary_contact_number" type="tel" value="{{ old('secondary_contact_number', $employee->details?->secondary_contact_number) }}">
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Emergency contact name <span class="text-pedago-red">Required</span>
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="emergency_contact_name" value="{{ old('emergency_contact_name', $employee->details?->emergency_contact_name) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Emergency contact number <span class="text-pedago-red">Required</span>
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="emergency_contact_number" type="tel" value="{{ old('emergency_contact_number', $employee->details?->emergency_contact_number) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Blood group <span class="text-pedago-red">Required</span>
                    <select class="rounded-md border border-pedago-line bg-white px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="blood_group" required>
                        <option value="">Select a blood group</option>
                        @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bloodGroup)
                            <option value="{{ $bloodGroup }}" @selected(old('blood_group', $employee->details?->blood_group) === $bloodGroup)>{{ $bloodGroup }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium sm:col-span-2">National ID number
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="nid_number" value="{{ old('nid_number') }}" autocomplete="off">
                    <span class="font-normal text-pedago-muted">Stored encrypted. Leave empty unless adding or replacing it.</span>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium sm:col-span-2">Present address <span class="text-pedago-red">Required</span>
                    <textarea class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="present_address" rows="3" required>{{ old('present_address', $employee->details?->present_address) }}</textarea>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium sm:col-span-2">Permanent address <span class="text-pedago-red">Required</span>
                    <textarea class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="permanent_address" rows="3" required>{{ old('permanent_address', $employee->details?->permanent_address) }}</textarea>
                </label>
            </div>
        </section>

        <button class="self-start rounded-md bg-pedago-blue px-5 py-3 font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">Save profile</button>
    </form>

    {{-- Duty Time & Specific Date Override (Calendar) --}}
    <section class="rounded-lg border border-pedago-line bg-white p-6" aria-labelledby="duty-time-heading">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-pedago-line pb-4">
            <div>
                <h2 class="text-lg font-semibold text-pedago-ink" id="duty-time-heading">Duty Time & Schedule</h2>
                <p class="text-sm text-pedago-muted">Primary duty time is inherited from the assigned team. Overrides can be scheduled for specific dates using the calendar.</p>
            </div>
            @php($effectiveTeam = $employee->effectiveTeam())
            @if ($effectiveTeam)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-pedago-tint px-3 py-1 text-xs font-semibold text-pedago-blue-dark">
                    Team: {{ $effectiveTeam->name }}
                    @if ($employee->teamsLed->contains($effectiveTeam))
                        <span class="rounded bg-pedago-blue-dark/10 px-1 text-[10px]">Leader</span>
                    @endif
                </span>
            @endif
        </div>

        {{-- 1. Primary Team Duty Time --}}
        <div class="rounded-lg border border-pedago-line bg-slate-50/70 p-4">
            <span class="text-xs font-bold uppercase tracking-wider text-pedago-muted">Primary Duty Time (Team Default)</span>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-pedago-tint text-pedago-blue-dark">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div>
                        @if ($effectiveTeam && $effectiveTeam->shift)
                            <p class="font-bold text-pedago-ink">
                                {{ \Carbon\Carbon::parse($effectiveTeam->shift->shift_start_time)->format('h:i A') }} – {{ \Carbon\Carbon::parse($effectiveTeam->shift->shift_end_time)->format('h:i A') }}
                                <span class="ml-1 text-xs font-normal text-pedago-muted">({{ $effectiveTeam->shift->working_hours }} hrs/day)</span>
                            </p>
                            <p class="text-xs text-pedago-muted">Inherited from <a class="text-pedago-blue hover:underline" href="{{ route('teams.show', $effectiveTeam) }}">{{ $effectiveTeam->name }}</a></p>
                        @elseif ($effectiveTeam)
                            <p class="font-semibold text-pedago-ink">No primary duty time set on team {{ $effectiveTeam->name }}</p>
                            <p class="text-xs text-pedago-muted">Duty time can be fixed in <a class="text-pedago-blue hover:underline" href="{{ route('teams.edit', $effectiveTeam) }}">Team edit</a>.</p>
                        @elseif ($employee->defaultShift)
                            <p class="font-bold text-pedago-ink">
                                {{ \Carbon\Carbon::parse($employee->defaultShift->shift_start_time)->format('h:i A') }} – {{ \Carbon\Carbon::parse($employee->defaultShift->shift_end_time)->format('h:i A') }}
                                <span class="ml-1 text-xs font-normal text-pedago-muted">(Individual default)</span>
                            </p>
                            <p class="text-xs text-pedago-muted">Individual default shift.</p>
                        @else
                            <p class="font-semibold text-pedago-ink">No team or shift assigned</p>
                            <p class="text-xs text-pedago-muted">Assign this employee to a team with a shift, or configure a date override below.</p>
                        @endif
                    </div>
                </div>

                @if ($employee->team && $isAdminEdit)
                    <a class="inline-flex items-center gap-1 rounded-md border border-pedago-line bg-white px-3 py-1.5 text-xs font-semibold text-pedago-ink transition hover:bg-pedago-canvas" href="{{ route('teams.edit', $employee->team) }}">
                        <span>Edit team duty time</span>
                        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        @if ($isAdminEdit)
            {{-- 2. Specific Date Override Form (Calendar) --}}
            <div class="mt-6 border-t border-pedago-line pt-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-pedago-ink">Override duty time for a specific date (Calendar)</h3>
                        <p class="text-xs text-pedago-muted">Select a date from the calendar to assign a custom shift or mark as day off for that date only.</p>
                    </div>
                </div>

                <form class="mt-4 flex flex-col gap-4 rounded-lg border border-pedago-line bg-pedago-canvas/30 p-4" method="POST" action="{{ route('employees.roster-override.store', $employee) }}" id="roster-override-form">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                        {{-- Calendar Date Selector --}}
                        <div>
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-ink" for="roster-override-date">
                                Calendar Date <span class="text-pedago-red">*</span>
                                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="roster-override-date" name="roster_date" type="date" value="{{ old('roster_date', today()->toDateString()) }}" required>
                            </label>
                            @error('roster_date')<span class="text-xs text-pedago-red">{{ $message }}</span>@enderror
                        </div>

                        {{-- Duty Status --}}
                        <div>
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-ink" for="roster-is-on-duty">
                                Duty on that date <span class="text-pedago-red">*</span>
                                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="roster-is-on-duty" name="is_on_duty">
                                    <option value="1" @selected(old('is_on_duty', '1') === '1')>On Duty (Work shift)</option>
                                    <option value="0" @selected(old('is_on_duty') === '0')>Day Off / Holiday</option>
                                </select>
                            </label>
                        </div>

                        {{-- Predefined Shift --}}
                        <div id="override-shift-container">
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-ink" for="roster-shift-id">
                                Shift for this date
                                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="roster-shift-id" name="shift_id">
                                    <option value="">-- Inherit team shift --</option>
                                    @foreach (($shifts ?? []) as $s)
                                        <option value="{{ $s->id }}" @selected((string) old('shift_id') === (string) $s->id)>
                                            {{ \Carbon\Carbon::parse($s->shift_start_time)->format('h:i A') }} – {{ \Carbon\Carbon::parse($s->shift_end_time)->format('h:i A') }} ({{ $s->working_hours }}h)
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        {{-- Availability Status --}}
                        <div id="override-status-container">
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-ink" for="roster-avail-status">
                                Availability
                                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="roster-avail-status" name="availability_status">
                                    <option value="Available" @selected(old('availability_status') === 'Available')>Available</option>
                                    <option value="Busy" @selected(old('availability_status') === 'Busy')>Busy</option>
                                    <option value="Cut Off" @selected(old('availability_status') === 'Cut Off')>Cut Off</option>
                                    <option value="Holiday" @selected(old('availability_status') === 'Holiday')>Holiday</option>
                                    <option value="Unavailable" @selected(old('availability_status') === 'Unavailable')>Unavailable</option>
                                    <option value="Off Duty" @selected(old('availability_status') === 'Off Duty')>Off Duty</option>
                                    <option value="Lazy" @selected(old('availability_status') === 'Lazy')>Lazy</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    {{-- Custom start / end times option --}}
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" id="override-custom-times">
                        <div>
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-ink" for="roster-shift-start">
                                Custom Start Time (optional)
                                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="roster-shift-start" name="shift_start_time" type="time" value="{{ old('shift_start_time') }}">
                            </label>
                        </div>
                        <div>
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-pedago-ink" for="roster-shift-end">
                                Custom End Time (optional)
                                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="roster-shift-end" name="shift_end_time" type="time" value="{{ old('shift_end_time') }}">
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <button class="inline-flex items-center gap-1.5 rounded-md bg-pedago-blue px-4 py-2 text-xs font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-pedago-blue" type="submit">
                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span>Save date override</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- 3. Scheduled / Active Overrides List --}}
        <div class="mt-6 border-t border-pedago-line pt-5">
            <h3 class="text-sm font-bold text-pedago-ink">Active & upcoming specific date overrides</h3>
            <p class="text-xs text-pedago-muted">Dates listed below take precedence over the team's primary duty time.</p>

            <div class="mt-3 overflow-hidden rounded-lg border border-pedago-line">
                <table class="w-full text-left text-xs">
                    <thead class="bg-pedago-canvas text-[11px] uppercase tracking-wider text-pedago-muted">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Calendar Date</th>
                            <th class="px-4 py-2.5 font-semibold">Duty Mode</th>
                            <th class="px-4 py-2.5 font-semibold">Shift Hours</th>
                            <th class="px-4 py-2.5 font-semibold">Availability</th>
                            @if ($isAdminEdit)
                                <th class="px-4 py-2.5 font-semibold text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pedago-line bg-white">
                        @forelse (($dateOverrides ?? []) as $override)
                            @php($isPast = $override->roster_date->isPast() && ! $override->roster_date->isToday())
                            <tr @class(['hover:bg-pedago-canvas/40 transition-colors', 'opacity-60 bg-slate-50/50' => $isPast])>
                                <td class="px-4 py-3 font-semibold text-pedago-ink">
                                    {{ $override->roster_date->format('l, M d, Y') }}
                                    @if ($override->roster_date->isToday())
                                        <span class="ml-1 rounded bg-pedago-tint px-1.5 py-0.5 text-[10px] font-bold text-pedago-blue-dark">Today</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($override->is_on_duty)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-pedago-green-tint px-2 py-0.5 text-[11px] font-semibold text-pedago-green">
                                            ● On Duty
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-pedago-amber-tint px-2 py-0.5 text-[11px] font-semibold text-pedago-amber">
                                            ○ Day Off / Holiday
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 num">
                                    @if ($override->is_on_duty && $override->shift)
                                        {{ \Carbon\Carbon::parse($override->shift->shift_start_time)->format('h:i A') }} – {{ \Carbon\Carbon::parse($override->shift->shift_end_time)->format('h:i A') }}
                                        <span class="text-pedago-muted">({{ $override->shift->working_hours }}h)</span>
                                    @elseif ($override->is_on_duty && $employee->team?->shift)
                                        {{ \Carbon\Carbon::parse($employee->team->shift->shift_start_time)->format('h:i A') }} – {{ \Carbon\Carbon::parse($employee->team->shift->shift_end_time)->format('h:i A') }}
                                        <span class="text-pedago-muted">(Team default)</span>
                                    @else
                                        <span class="text-pedago-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php($avSt = $override->availability_status ?? ($override->is_on_duty ? 'Available' : 'Holiday'))
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold',
                                        'bg-pedago-green-tint text-pedago-green' => $avSt === 'Available',
                                        'bg-blue-50 text-blue-800' => $avSt === 'Busy',
                                        'bg-pedago-tint text-pedago-blue-dark' => $avSt === 'Cut Off',
                                        'bg-pedago-amber-tint text-pedago-amber' => $avSt === 'Holiday',
                                        'bg-pedago-slate-tint text-pedago-muted' => $avSt === 'Off Duty',
                                        'bg-pedago-red-tint text-pedago-red' => $avSt === 'Lazy',
                                        'bg-pedago-amber-tint text-pedago-amber' => in_array($avSt, ['Unavailable', 'Break'], true),
                                        'bg-pedago-slate-tint text-pedago-muted' => $avSt === 'Resigned',
                                    ])>
                                        {{ $avSt }}
                                    </span>
                                </td>
                                @if ($isAdminEdit)
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('employees.roster-override.destroy', [$employee, $override]) }}" onsubmit="return confirm('Remove this date override and revert to team primary duty time?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded border border-pedago-line px-2.5 py-1 text-[11px] font-semibold text-pedago-red transition hover:bg-pedago-red-tint hover:border-pedago-red-line" type="submit">
                                                Revert to team
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-xs text-pedago-muted" colspan="{{ $isAdminEdit ? 5 : 4 }}">
                                    No date overrides found. This employee follows the team's primary duty time.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        const dutySelect = document.getElementById('roster-is-on-duty');
        const shiftContainer = document.getElementById('override-shift-container');
        const customTimes = document.getElementById('override-custom-times');
        const statusSelect = document.getElementById('roster-avail-status');

        if (dutySelect) {
            dutySelect.addEventListener('change', () => {
                const isOnDuty = dutySelect.value === '1';
                if (shiftContainer) shiftContainer.style.display = isOnDuty ? 'block' : 'none';
                if (customTimes) customTimes.style.display = isOnDuty ? 'grid' : 'none';
                if (statusSelect) {
                    statusSelect.value = isOnDuty ? 'Available' : 'Holiday';
                }
            });
        }
    </script>
@endpush
