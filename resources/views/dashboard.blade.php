@extends('layouts.app', ['title' => 'Dashboard'])

@section('page-title', $employee->isAdmin() ? 'Admin dashboard' : 'Dashboard')
@section('page-description', $employee->isAdmin() ? 'Live operations, with lead totals for '.$rangeLabel.'.' : 'Welcome back, '.$employee->name.'.')

@section('content')
    @if ($employee->isAdmin())
        @php
            $taka = fn (float $amount): string => '৳ '.number_format($amount);
            $waiting = fn ($moment): string => $moment === null ? '' : \Illuminate\Support\Carbon::parse($moment)->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'short' => true]);
            $maxPipeline = max(1, (int) $pipeline->max());
        @endphp

        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6" aria-label="Live operations">
            <x-ui.stat label="Unassigned queue" :value="number_format($metrics['unassigned_leads'])" :href="route('central-queue.index')"
                :hint="$metrics['available_executives'].' '.Str::plural('executive', $metrics['available_executives']).' available'" />
            <x-ui.stat label="Active batches" :value="number_format($metrics['active_batches'])" :href="route('teams.index')"
                :hint="'of '.$metrics['on_duty'].' on duty today'" />
            <x-ui.stat label="Overdue follow-ups" :value="number_format($metrics['overdue_followups'])" :href="route('follow-ups.index')" :alert="$metrics['overdue_followups'] > 0"
                :hint="$metrics['lazy_executives'].' '.Str::plural('executive', $metrics['lazy_executives']).' flagged Lazy'" />
            <x-ui.stat label="Follow-ups coming up" :value="number_format($metrics['coming_followups'])" :href="route('follow-ups.index', ['tab' => 'soon'])" hint="Within the reminder window" />
            <x-ui.stat label="Payments under review" :value="number_format($metrics['payments_pending'])" :href="route('payments.index')"
                :hint="$metrics['oldest_payment_at'] ? 'Oldest waiting '.$waiting($metrics['oldest_payment_at']) : 'Nothing waiting'" />
            <x-ui.stat label="Pending transfers" :value="number_format($metrics['transfers_pending'])" :href="route('lead-transfers.index')"
                :hint="$metrics['next_auto_transfer_at'] ? ($metrics['next_auto_transfer_at']->isPast() ? 'Auto-approval is due' : 'Next auto-approval in '.$waiting($metrics['next_auto_transfer_at'])) : 'Nothing waiting'" />
            <x-ui.stat label="Converted today" :value="number_format($metrics['converted_today'])" :href="route('payments.index', ['status' => 'Verified'])"
                :hint="$taka($metrics['collected_today']).' collected'" />
        </section>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
            <x-ui.card title="Distribution engine — next cycle">
                <x-slot:meta>
                    <x-ui.pill :tone="$engineEnabled ? 'green' : 'slate'">Automatic: {{ $engineEnabled ? 'on' : 'off' }}</x-ui.pill>
                </x-slot:meta>

                <dl class="mb-3 grid grid-cols-3 gap-2.5">
                    @foreach (['Queue leads (L)' => number_format($plan['queue']), 'Available (S)' => number_format($plan['executives']), 'Rule' => $plan['rule']] as $term => $figure)
                        <div class="rounded-md bg-pedago-canvas px-2.5 py-2">
                            <dt class="flex items-center gap-1 text-xs text-pedago-muted">{{ $term }}<x-ui.info :term="$term" /></dt>
                            <dd class="num truncate text-lg font-semibold" title="{{ $figure }}">{{ $figure }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($plan['allocations'] !== [])
                    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 2xl:grid-cols-6">
                        @foreach (array_slice($plan['allocations'], 0, 12) as $allocation)
                            <li class="rounded-md border border-pedago-line px-2 py-1.5 text-xs text-pedago-muted">
                                <span class="block truncate text-[13px] font-semibold text-pedago-ink" title="{{ $allocation['employee']->name }}">{{ $allocation['employee']->name }}</span>
                                {{ $allocation['count'] }} {{ Str::plural('lead', $allocation['count']) }}
                                <span class="mt-1 flex gap-1" aria-hidden="true">
                                    @for ($dot = 0; $dot < $allocation['count']; $dot++)
                                        <span @class(['size-2.5 rounded-sm', 'bg-pedago-blue' => $dot < $allocation['base'], 'border-2 border-pedago-red bg-white' => $dot >= $allocation['base']])></span>
                                    @endfor
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if (count($plan['allocations']) > 12)
                        <p class="mt-2 text-xs text-pedago-muted">and {{ count($plan['allocations']) - 12 }} more executives.</p>
                    @endif
                @endif

                <p class="mt-3 text-[13px] leading-5 text-pedago-muted">{{ $plan['note'] }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-ui.button :href="route('central-queue.index')" variant="secondary" size="sm" icon="inbox">Open central queue</x-ui.button>
                    @if ($plan['assignable'] > 0)
                        <form method="POST" action="{{ route('central-queue.distribute') }}" data-confirm="Assign {{ $plan['assignable'] }} queue {{ Str::plural('lead', $plan['assignable']) }} to {{ count($plan['allocations']) }} {{ Str::plural('executive', count($plan['allocations'])) }} now?">
                            @csrf
                            <x-ui.button type="submit" size="sm" icon="zap">Run this cycle now</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card title="Lead pipeline">
                <x-slot:meta>
                    {{ number_format($metrics['total_leads']) }} Total Leads · {{ $rangeLabel }}
                </x-slot:meta>

                <form class="mb-3 flex flex-wrap items-center gap-1.5" method="GET" action="{{ route('dashboard') }}" aria-label="Dashboard date range">
                    @foreach (['today' => 'Today', 'this_month' => 'This month', 'three_months' => '3 months', 'this_year' => 'This year'] as $preset => $label)
                        <button @class([
                            'min-h-8 rounded-md border px-2.5 text-xs font-semibold transition-colors duration-150',
                            'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' => $range === $preset,
                            'border-pedago-line text-pedago-ink hover:bg-pedago-canvas' => $range !== $preset,
                        ]) type="submit" name="range" value="{{ $preset }}" aria-pressed="{{ $range === $preset ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                    <span class="flex flex-wrap items-center gap-1.5">
                        <label class="sr-only" for="dashboard-start-date">Custom start date</label>
                        <input class="h-8 w-32 rounded-md border border-pedago-line px-2 text-xs" id="dashboard-start-date" name="start_date" type="date" value="{{ $range === 'custom' ? $rangeStart->toDateString() : '' }}">
                        <label class="sr-only" for="dashboard-end-date">Custom end date</label>
                        <input class="h-8 w-32 rounded-md border border-pedago-line px-2 text-xs" id="dashboard-end-date" name="end_date" type="date" value="{{ $range === 'custom' ? $rangeEnd->toDateString() : '' }}">
                        <button @class([
                            'min-h-8 rounded-md border px-2.5 text-xs font-semibold transition-colors duration-150',
                            'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' => $range === 'custom',
                            'border-pedago-line text-pedago-ink hover:bg-pedago-canvas' => $range !== 'custom',
                        ]) type="submit" name="range" value="custom" aria-pressed="{{ $range === 'custom' ? 'true' : 'false' }}">Apply</button>
                    </span>
                </form>

                @if ($pipeline->isEmpty())
                    <x-ui.empty title="No leads in this period" icon="inbox">Choose a wider date range, or upload leads to start the pipeline.</x-ui.empty>
                @else
                    <ul class="flex flex-col gap-1.5">
                        @foreach ($pipelineOrder as $status)
                            @continue(! $pipeline->has($status))
                            @php $count = (int) $pipeline->get($status); @endphp
                            <li class="grid grid-cols-[8.5rem_minmax(0,1fr)_2.75rem] items-center gap-2.5 text-[13px]">
                                <span class="truncate" title="{{ $status }}">{{ $status }}</span>
                                <span class="block h-3.5 rounded-sm bg-pedago-canvas">
                                    <span @class([
                                        'block h-full rounded-sm',
                                        'bg-pedago-green' => $status === 'Converted',
                                        'bg-pedago-blue' => in_array($status, ['Assigned', 'Contacted', 'Follow-up', 'Interested'], true),
                                        'bg-pedago-blue-soft' => ! in_array($status, ['Converted', 'Assigned', 'Contacted', 'Follow-up', 'Interested'], true),
                                    ]) style="width: {{ max(2, (int) round($count / $maxPipeline * 100)) }}%"></span>
                                </span>
                                <span class="num text-right text-xs font-semibold">{{ number_format($count) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <x-ui.card title="Executive roster — today" flush>
                <x-slot:meta>
                    Showing {{ $roster->count() }} of {{ $rosterTotal }} · <a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('teams.index') }}">View all</a>
                </x-slot:meta>

                @if ($roster->isEmpty())
                    <x-ui.empty title="No active sales executives" icon="users">Add sales executives and put them in a team to see today’s roster.</x-ui.empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-left">
                            <thead>
                                <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    <th class="px-4 py-2 font-semibold sm:px-5" scope="col">Executive</th>
                                    <th class="px-2 py-2 font-semibold" scope="col">Team</th>
                                    <th class="px-2 py-2 font-semibold" scope="col">Shift</th>
                                    <th class="px-2 py-2 font-semibold" scope="col">Status</th>
                                    <th class="px-2 py-2 font-semibold" scope="col"><span class="inline-flex items-center gap-1">Batch<x-ui.info term="Batch" /></span></th>
                                    <th class="px-2 py-2 font-semibold" scope="col">Overdue</th>
                                    <th class="px-4 py-2 sm:px-5" scope="col"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody class="text-[13px]">
                                @foreach ($roster as $row)
                                    <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                        <th class="whitespace-nowrap px-4 py-2 font-medium sm:px-5" scope="row">{{ $row['employee']->name }}</th>
                                        <td class="whitespace-nowrap px-2 py-2">{{ $row['team'] ?? '—' }}</td>
                                        <td class="num whitespace-nowrap px-2 py-2 text-xs">{{ $row['shift'] ?? 'Day off' }}</td>
                                        <td class="px-2 py-2"><x-ui.pill :status="$row['status']" /></td>
                                        <td class="whitespace-nowrap px-2 py-2">
                                            @if ($row['batch_total'] > 0)
                                                <span class="mr-1.5 inline-block h-1.5 w-14 overflow-hidden rounded-full bg-pedago-line align-middle" aria-hidden="true">
                                                    <span class="block h-full bg-pedago-blue" style="width: {{ (int) round($row['batch_done'] / $row['batch_total'] * 100) }}%"></span>
                                                </span><span class="num text-xs">{{ $row['batch_done'] }}/{{ $row['batch_total'] }}</span>
                                            @else
                                                <span class="text-pedago-muted">—</span>
                                            @endif
                                        </td>
                                        <td @class(['num px-2 py-2 text-xs', 'font-semibold text-pedago-red' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                                        <td class="px-4 py-2 text-right sm:px-5">
                                            @if ($row['overdue'] > 0)
                                                <x-ui.button :href="route('follow-ups.index', ['employee_id' => $row['employee']->id])" variant="secondary" size="sm" aria-label="Review overdue follow-ups of {{ $row['employee']->name }}">Review</x-ui.button>
                                            @else
                                                <x-ui.button :href="route('employees.edit', $row['employee'])" variant="secondary" size="sm" aria-label="Open profile of {{ $row['employee']->name }}">Open</x-ui.button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Needs your attention">
                <h3 class="text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Payments to verify</h3>
                <ul class="divide-y divide-pedago-line">
                    @forelse ($attention['payments'] as $payment)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-semibold">{{ $taka((float) $payment->amount) }} · {{ $payment->payment_method }}</p>
                                <p class="truncate text-xs text-pedago-muted">{{ $payment->enrollment?->student_name }}{{ $payment->installmentSchedule ? ' · Installment '.$payment->installmentSchedule->installment_number : '' }}</p>
                            </div>
                            <x-ui.button :href="route('payments.index')" variant="secondary" size="sm">Verify</x-ui.button>
                        </li>
                    @empty
                        <li class="py-2 text-[13px] text-pedago-muted">No payment is waiting for review.</li>
                    @endforelse
                </ul>

                <h3 class="mt-3 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Transfers awaiting approval</h3>
                <ul class="divide-y divide-pedago-line">
                    @forelse ($attention['transfers'] as $transfer)
                        @php $day = min($autoApproveDays, (int) $transfer->created_at->diffInDays(now()) + 1); @endphp
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-semibold">{{ $transfer->fromTeam?->name }} → {{ $transfer->toTeam?->name }}</p>
                                <p class="truncate text-xs text-pedago-muted">Day {{ $day }} of {{ $autoApproveDays }} · auto-approves at day {{ $autoApproveDays }}</p>
                            </div>
                            <x-ui.button :href="route('lead-transfers.index')" variant="secondary" size="sm">Decide</x-ui.button>
                        </li>
                    @empty
                        <li class="py-2 text-[13px] text-pedago-muted">No transfer is waiting for a decision.</li>
                    @endforelse
                </ul>

                <h3 class="mt-3 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Installments due in {{ $reminderDays }} {{ Str::plural('day', $reminderDays) }}</h3>
                <ul class="divide-y divide-pedago-line">
                    @forelse ($attention['installments'] as $installment)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-semibold">Installment {{ $installment->installment_number }} · {{ $taka((float) $installment->due_amount) }}</p>
                                <p class="truncate text-xs text-pedago-muted">{{ $installment->enrollment?->student_name }} · due {{ $installment->due_date->format('M j') }}</p>
                            </div>
                            <x-ui.pill tone="amber">T−{{ max(0, (int) today()->diffInDays($installment->due_date)) }}</x-ui.pill>
                        </li>
                    @empty
                        <li class="py-2 text-[13px] text-pedago-muted">No installment falls due in this window.</li>
                    @endforelse
                </ul>

                <h3 class="mt-3 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Other queues</h3>
                <ul class="divide-y divide-pedago-line">
                    <li class="flex items-center justify-between gap-3 py-2">
                        <p class="text-[13px] font-semibold">Duplicate reviews pending</p>
                        <x-ui.button :href="route('duplicate-lead-reviews.index')" variant="secondary" size="sm">Review {{ number_format($metrics['duplicate_reviews']) }}</x-ui.button>
                    </li>
                    <li class="flex items-center justify-between gap-3 py-2">
                        <p class="text-[13px] font-semibold">Employee change requests</p>
                        <x-ui.button :href="route('employee-change-requests.index')" variant="secondary" size="sm">Review {{ number_format($pendingChanges) }}</x-ui.button>
                    </li>
                </ul>
            </x-ui.card>
        </div>
    @else
        @foreach ($panels as $panelName)
            @if (count($panels) > 1)
                <h2 class="-mb-1 mt-1 text-xs font-semibold uppercase tracking-[0.08em] text-pedago-muted">{{ $panelName === 'executive' ? 'My work' : 'My team' }}</h2>
            @endif
            @include('panels.'.$panelName)
        @endforeach

        @if ($panels === [])
            <x-ui.card>
                <p class="text-sm font-semibold uppercase tracking-wide text-pedago-blue">{{ $employee->designation }}</p>
                <p class="mt-1 text-sm text-pedago-muted">{{ $employee->office_id }} · {{ $employee->role }}</p>
            </x-ui.card>
        @endif

        <section class="grid gap-3 sm:grid-cols-2" aria-label="Shortcuts">
            @php
                $shortcuts = [
                    ['Leave', route('leave.index'), 'Apply for leave and follow your requests.', true],
                    ['Performance', route('performance.index'), 'See your KPIs against the targets for this period.', true],
                    ['My profile', route('profile.edit'), 'Update personal details and submit essential employment changes for approval.', true],
                ];
                $shortcuts[] = ['Leave requests', route('leave.requests'), 'Decide leave for the people you manage.', in_array($employee->role, ['Supervisor', 'Team Leader'], true)];
                $shortcuts[] = ['Review employee changes', route('employee-change-requests.index'), 'Review requests submitted by employees below your authority level.', $employee->lowerAuthorityRoles() !== []];
                $shortcuts[] = ['Upload leads', route('leads.upload'), 'Import a validated CSV file and send phone matches to duplicate review.', $employee->role === 'MarketingExecutive'];
                $shortcuts[] = ['Payments review', route('payments.index'), 'Verify or reject payments that students have submitted.', $employee->role === 'Accounts'];
                $shortcuts[] = ['Installments', route('installments.index'), 'See which installments are due soon, overdue or paid.', $employee->role === 'Accounts'];
            @endphp
            @foreach ($shortcuts as [$heading, $url, $text, $visible])
                @continue(! $visible)
                <a class="rounded-lg border border-pedago-line bg-white p-5 transition-colors duration-150 hover:border-pedago-blue hover:bg-pedago-row" href="{{ $url }}">
                    <h2 class="font-semibold">{{ $heading }}</h2>
                    <p class="mt-1.5 text-sm text-pedago-muted">{{ $text }}</p>
                </a>
            @endforeach
        </section>
    @endif
@endsection
