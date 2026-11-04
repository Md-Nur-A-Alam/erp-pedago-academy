@extends('layouts.app', ['title' => 'Employees'])

@section('page-title', 'Employees')
@section('page-description', 'Manage employee accounts, office roles, and profile information.')
@section('page-actions')
    <a class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" href="{{ route('employees.create') }}">Add employee</a>
@endsection

@section('content')
    <section class="overflow-hidden rounded-xl border border-pedago-line bg-white shadow-sm" aria-labelledby="employee-list-heading">
        <div class="border-b border-pedago-line px-5 py-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-base font-bold" id="employee-list-heading">Employee directory</h2>
                <span class="text-xs text-pedago-muted">{{ number_format($employees->total()) }} employees</span>
            </div>
            <form class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,2fr)_minmax(11rem,1fr)_minmax(10rem,1fr)_auto]" method="GET" action="{{ route('employees.index') }}">
                <label class="sr-only" for="employee-search">Search employees</label>
                <input class="rounded-md border border-pedago-line px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="employee-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search name, office ID, or phone">
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
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="bg-pedago-canvas text-[11px] uppercase tracking-wide text-pedago-muted">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Employee</th>
                        <th class="px-5 py-3 font-semibold">Office ID</th>
                        <th class="px-5 py-3 font-semibold">Designation</th>
                        <th class="px-5 py-3 font-semibold">Role</th>
                        <th class="px-5 py-3 font-semibold">Login phone</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pedago-line">
                    @forelse ($employees as $employee)
                        <tr class="transition-colors hover:bg-pedago-canvas/70">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-pedago-ink">{{ $employee->name }}</p>
                                <p class="mt-0.5 text-xs text-pedago-muted">Joined {{ $employee->joining_date?->format('M j, Y') ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $employee->office_id }}</td>
                            <td class="px-5 py-3">{{ $employee->designation }}</td>
                            <td class="px-5 py-3">{{ $employee->role }}</td>
                            <td class="px-5 py-3">{{ $employee->user?->phone_number ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-pedago-green-tint text-pedago-green' => $employee->status === 'Active',
                                    'bg-pedago-red-tint text-pedago-red' => $employee->status === 'Suspended',
                                    'bg-pedago-canvas text-pedago-muted' => $employee->status === 'Inactive',
                                ])>{{ $employee->status }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('employees.edit', $employee) }}">View / edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-10 text-center text-pedago-muted" colspan="7">No employees match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())
            <div class="border-t border-pedago-line px-5 py-4">{{ $employees->links() }}</div>
        @endif
    </section>
@endsection
