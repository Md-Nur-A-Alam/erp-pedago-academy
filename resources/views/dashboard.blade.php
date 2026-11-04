@extends('layouts.app', ['title' => 'Dashboard'])

@section('page-title', $employee->isAdmin() ? 'Admin dashboard' : 'Dashboard')
@section('page-description', $employee->isAdmin() ? 'Your current operations snapshot.' : 'Welcome back, '.$employee->name.'.')

@section('content')
    @if ($employee->isAdmin())
        @php($maxPipeline = max(1, (int) $pipeline->max()))
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6" aria-label="Key metrics">
            @foreach ([
                ['Unassigned leads', $metrics['unassigned_leads'], 'New leads waiting in the queue', 'text-pedago-blue'],
                ['Active batches', $metrics['active_batches'], 'Lead batches in progress', 'text-pedago-blue'],
                ['Overdue follow-ups', $metrics['overdue_followups'], 'Follow-ups past their scheduled time', 'text-pedago-red'],
                ['Payments to review', $metrics['payments_pending'], 'Payments with pending status', 'text-pedago-amber'],
                ['Pending transfers', $metrics['transfers_pending'], 'Lead transfers awaiting a decision', 'text-pedago-blue'],
                ['Duplicate reviews', $metrics['duplicate_reviews'], 'Possible duplicate leads to review', 'text-pedago-amber'],
            ] as [$label, $value, $description, $color])
                <article class="rounded-lg border border-pedago-line bg-white p-4">
                    <p class="text-xs font-semibold text-pedago-muted">{{ $label }}</p>
                    <p class="mt-2 font-mono text-3xl font-semibold {{ $color }}">{{ number_format($value) }}</p>
                    <p class="mt-1 text-xs leading-5 text-pedago-muted">{{ $description }}</p>
                </article>
            @endforeach
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-lg border border-pedago-line bg-white p-5" aria-labelledby="pipeline-heading">
                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 class="text-sm font-bold" id="pipeline-heading">Lead pipeline</h2>
                    <span class="text-xs text-pedago-muted">Current leads by status</span>
                </div>
                <div class="flex flex-col gap-3">
                    @forelse ($pipeline as $status => $count)
                        @php($percentage = (int) round(($count / $maxPipeline) * 100))
                        <div class="grid grid-cols-[minmax(7rem,1fr)_minmax(4rem,3fr)_2.5rem] items-center gap-3 text-sm">
                            <span class="truncate text-pedago-muted">{{ $status }}</span>
                            <span class="h-3 overflow-hidden rounded-sm bg-pedago-canvas">
                                <span class="block h-full rounded-sm bg-pedago-blue" style="width: {{ $percentage }}%"></span>
                            </span>
                            <span class="text-right font-mono text-xs font-semibold">{{ number_format($count) }}</span>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-pedago-muted">No leads have been added yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-pedago-line bg-white p-5" aria-labelledby="attention-heading">
                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 class="text-sm font-bold" id="attention-heading">Needs your attention</h2>
                    <span class="text-xs text-pedago-muted">Items requiring review</span>
                </div>
                <ul class="divide-y divide-pedago-line">
                    <li class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-semibold">Employee change requests</p>
                            <p class="mt-1 text-xs text-pedago-muted">Essential profile changes awaiting approval</p>
                        </div>
                        <a class="shrink-0 rounded-md border border-pedago-line px-3 py-1.5 text-xs font-semibold text-pedago-blue-dark hover:bg-pedago-tint" href="{{ route('employee-change-requests.index') }}">
                            Review {{ number_format($pendingChanges) }}
                        </a>
                    </li>
                    <li class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-semibold">Payments pending</p>
                            <p class="mt-1 text-xs text-pedago-muted">Payment review actions are not available yet.</p>
                        </div>
                        <span class="rounded-full bg-pedago-amber-tint px-2.5 py-1 text-xs font-semibold text-pedago-amber">{{ number_format($metrics['payments_pending']) }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-semibold">Lead transfers pending</p>
                            <p class="mt-1 text-xs text-pedago-muted">Transfer decision actions are not available yet.</p>
                        </div>
                        <span class="rounded-full bg-pedago-tint px-2.5 py-1 text-xs font-semibold text-pedago-blue-dark">{{ number_format($metrics['transfers_pending']) }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-semibold">Duplicate reviews pending</p>
                            <p class="mt-1 text-xs text-pedago-muted">Compare and resolve possible lead matches.</p>
                        </div>
                        <a class="shrink-0 rounded-md border border-pedago-line px-3 py-1.5 text-xs font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('duplicate-lead-reviews.index') }}">
                            Review {{ number_format($metrics['duplicate_reviews']) }}
                        </a>
                    </li>
                </ul>
            </section>
        </div>

        <section class="overflow-hidden rounded-lg border border-pedago-line bg-white" aria-labelledby="roster-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-4">
                <h2 class="text-sm font-bold" id="roster-heading">Today’s on-duty roster</h2>
                <span class="text-xs text-pedago-muted">{{ $roster->count() }} employees shown</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] border-collapse text-left">
                    <thead class="bg-pedago-canvas text-[11px] uppercase tracking-wide text-pedago-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Employee</th>
                            <th class="px-5 py-3 font-semibold">Shift</th>
                            <th class="px-5 py-3 font-semibold">Availability</th>
                            <th class="px-5 py-3 font-semibold">Active batches</th>
                            <th class="px-5 py-3 font-semibold">Overdue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pedago-line text-sm">
                        @forelse ($roster as $entry)
                            <tr class="transition-colors hover:bg-pedago-canvas/70">
                                <td class="px-5 py-3">
                                    <p class="font-semibold">{{ $entry->employee->name }}</p>
                                    <p class="mt-0.5 text-xs text-pedago-muted">{{ $entry->employee->role }}</p>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs">
                                    {{ substr($entry->shift->shift_start_time, 0, 5) }}–{{ substr($entry->shift->shift_end_time, 0, 5) }}
                                </td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-pedago-green-tint text-pedago-green' => $entry->availability_status === 'Available',
                                        'bg-pedago-tint text-pedago-blue-dark' => $entry->availability_status === 'Busy',
                                        'bg-pedago-amber-tint text-pedago-amber' => in_array($entry->availability_status, ['Unavailable', 'Off Duty'], true),
                                        'bg-pedago-red-tint text-pedago-red' => in_array($entry->availability_status, ['Lazy', 'Resigned'], true),
                                    ])>{{ $entry->availability_status }}</span>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs">{{ $entry->employee->active_batches_count }}</td>
                                <td @class([
                                    'px-5 py-3 font-mono text-xs',
                                    'font-semibold text-pedago-red' => $entry->employee->overdue_followups_count > 0,
                                ])>{{ $entry->employee->overdue_followups_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-8 text-center text-sm text-pedago-muted" colspan="5">No employees are marked on duty for today.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    @else
        <section class="rounded-lg border border-pedago-line bg-white p-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-pedago-blue">{{ $employee->designation }}</p>
            <p class="mt-1 text-sm text-pedago-muted">{{ $employee->office_id }} · {{ $employee->role }}</p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            <a class="rounded-lg border border-pedago-line bg-white p-5 hover:border-pedago-blue" href="{{ route('profile.edit') }}">
                <h2 class="font-semibold">My profile</h2>
                <p class="mt-2 text-sm text-pedago-muted">Update personal details and submit essential employment changes for approval.</p>
            </a>
            @if ($employee->lowerAuthorityRoles() !== [])
                <a class="rounded-lg border border-pedago-line bg-white p-5 hover:border-pedago-blue" href="{{ route('employee-change-requests.index') }}">
                    <h2 class="font-semibold">Review employee changes</h2>
                    <p class="mt-2 text-sm text-pedago-muted">Review requests submitted by employees below your authority level.</p>
                </a>
            @endif
            @if ($employee->role === 'MarketingExecutive')
                <a class="rounded-lg border border-pedago-line bg-white p-5 hover:border-pedago-blue" href="{{ route('leads.upload') }}">
                    <h2 class="font-semibold">Upload leads</h2>
                    <p class="mt-2 text-sm text-pedago-muted">Import a validated CSV file and send phone matches to duplicate review.</p>
                </a>
            @endif
            @if ($employee->isAdmin())
                <a class="rounded-lg border border-pedago-line bg-white p-5 hover:border-pedago-blue" href="{{ route('employees.index') }}">
                    <h2 class="font-semibold">Manage employees</h2>
                    <p class="mt-2 text-sm text-pedago-muted">Create accounts and maintain essential employee information.</p>
                </a>
            @endif
        </section>
    @endif
@endsection
