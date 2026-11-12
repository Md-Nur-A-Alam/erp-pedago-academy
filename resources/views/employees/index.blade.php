@extends('layouts.app', ['title' => 'Employees'])

@section('page-title', 'Employees')
@section('page-description', 'Manage employee accounts, office roles, and profile information.')
@section('page-actions')
    <a class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" href="{{ route('employees.create') }}">Add employee</a>
@endsection

@section('content')
    @php
        $currentSort = $filters['sort'] ?? 'name';
        $currentDirection = $filters['direction'] ?? 'asc';
        $sortUrl = function (string $col) use ($currentSort, $currentDirection) {
            $nextDir = ($currentSort === $col && $currentDirection === 'asc') ? 'desc' : 'asc';
            return route('employees.index', array_merge(request()->query(), [
                'sort' => $col,
                'direction' => $nextDir,
                'page' => 1,
            ]));
        };
    @endphp

    <section class="overflow-hidden rounded-xl border border-pedago-line bg-white shadow-sm" aria-labelledby="employee-list-heading">
        <div class="border-b border-pedago-line px-5 py-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-base font-bold" id="employee-list-heading">Employee directory</h2>
                <div class="flex items-center gap-3 text-xs text-pedago-muted">
                    <span>{{ number_format($employees->total()) }} employees</span>
                    <span aria-hidden="true">·</span>
                    <span>Sorted by <strong class="text-pedago-ink">{{ match($currentSort) {
                        'name' => 'Name',
                        'office_id' => 'Office ID',
                        'team' => 'Team',
                        'designation' => 'Designation',
                        'role' => 'Role',
                        'phone' => 'Phone',
                        'status' => 'Status',
                        'joining_date' => 'Joining Date',
                        default => ucfirst($currentSort),
                    } }} ({{ strtoupper($currentDirection) }})</strong></span>
                </div>
            </div>
            <form class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,2fr)_minmax(11rem,1fr)_minmax(10rem,1fr)_auto]" method="GET" action="{{ route('employees.index') }}">
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">

                <label class="sr-only" for="employee-search">Search employees</label>
                <input class="rounded-md border border-pedago-line px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="employee-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search name, office ID, team, or phone">
                <label class="sr-only" for="employee-role">Filter by role</label>
                <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="employee-role" name="role">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected($filters['role'] === $role)>{{ $role }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="employee-status">Filter by status</label>
                <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="employee-status" name="status">
                    <option value="">All statuses</option>
                    @foreach (['Active', 'Inactive', 'Suspended'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button class="flex-1 rounded-md border border-pedago-line px-4 py-2.5 text-sm font-semibold transition hover:border-pedago-blue hover:bg-pedago-tint sm:flex-none" type="submit">Filter</button>
                    <a class="rounded-md px-3 py-2.5 text-sm font-semibold text-pedago-muted transition hover:bg-pedago-canvas" href="{{ route('employees.index') }}">Clear</a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[950px] text-left text-sm">
                <thead class="bg-pedago-canvas text-[11px] uppercase tracking-wide text-pedago-muted">
                    <tr>
                        @foreach ([
                            'name' => 'Employee',
                            'office_id' => 'Office ID',
                            'team' => 'Team',
                            'designation' => 'Designation',
                            'role' => 'Role',
                            'phone' => 'Login phone',
                            'status' => 'Status',
                        ] as $colKey => $colLabel)
                            <th class="px-5 py-3 font-semibold">
                                <a class="group inline-flex items-center gap-1.5 transition hover:text-pedago-ink {{ $currentSort === $colKey ? 'font-bold text-pedago-blue-dark' : '' }}" href="{{ $sortUrl($colKey) }}" title="Sort by {{ $colLabel }}">
                                    <span>{{ $colLabel }}</span>
                                    <span class="inline-flex size-3.5 items-center justify-center">
                                        @if ($currentSort === $colKey)
                                            @if ($currentDirection === 'asc')
                                                <svg class="size-3.5 text-pedago-blue" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd" />
                                                </svg>
                                            @else
                                                <svg class="size-3.5 text-pedago-blue" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        @else
                                            <svg class="size-3 text-slate-300 opacity-60 transition group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="m7 15 5 5 5-5" />
                                                <path d="m7 9 5-5 5 5" />
                                            </svg>
                                        @endif
                                    </span>
                                </a>
                            </th>
                        @endforeach
                        <th class="px-5 py-3 font-semibold text-pedago-muted">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pedago-line">
                    @forelse ($employees as $employee)
                        <tr class="transition-colors hover:bg-pedago-canvas/70">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-pedago-ink">{{ $employee->name }}</p>
                                <p class="mt-0.5 text-xs text-pedago-muted">Joined {{ $employee->joining_date?->format('M j, Y') ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-pedago-ink">{{ $employee->office_id }}</td>
                            <td class="px-5 py-3">
                                @if ($employee->team)
                                    <a class="font-medium text-pedago-blue-dark transition hover:underline" href="{{ route('teams.show', $employee->team) }}">
                                        {{ $employee->team->name }}
                                    </a>
                                @elseif ($employee->teamsLed->isNotEmpty())
                                    <div class="flex items-center gap-1.5">
                                        <a class="font-medium text-pedago-blue-dark transition hover:underline" href="{{ route('teams.show', $employee->teamsLed->first()) }}">
                                            {{ $employee->teamsLed->first()->name }}
                                        </a>
                                        <span class="rounded bg-pedago-tint px-1.5 py-0.5 text-[10px] font-semibold text-pedago-blue-dark">Leader</span>
                                    </div>
                                @elseif ($employee->supervisedTeams->isNotEmpty())
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="font-medium text-pedago-ink">{{ $employee->supervisedTeams->pluck('name')->join(', ') }}</span>
                                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">Supervisor</span>
                                    </div>
                                @else
                                    <span class="text-xs text-pedago-muted">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">{{ $employee->designation }}</td>
                            <td class="px-5 py-3">{{ $employee->role }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $employee->user?->phone_number ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-pedago-green-tint text-pedago-green' => $employee->status === 'Active',
                                    'bg-pedago-red-tint text-pedago-red' => $employee->status === 'Suspended',
                                    'bg-pedago-canvas text-pedago-muted' => $employee->status === 'Inactive',
                                ])>{{ $employee->status }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <a class="inline-flex size-8 items-center justify-center rounded-md border border-pedago-line text-pedago-muted transition hover:border-pedago-blue hover:bg-pedago-tint hover:text-pedago-blue-dark" href="{{ route('employees.edit', $employee) }}" title="View / edit {{ $employee->name }}" aria-label="View / edit {{ $employee->name }}">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                                        <path d="m15 5 4 4"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-10 text-center text-pedago-muted" colspan="8">No employees match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())
            <div class="border-t border-pedago-line px-5 py-4">{{ $employees->links() }}</div>
        @endif
    </section>
@endsection
