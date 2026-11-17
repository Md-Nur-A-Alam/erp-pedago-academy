@php
    $pct = fn ($value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
    $minutes = fn ($value): string => $value === null ? '—' : ($value >= 120 ? round($value / 60, 1).' h' : $value.' min');
    $bar = fn ($value, $target, $higher = true) => $value === null || $target <= 0 ? null : ($higher ? $value / $target * 100 : ($value <= 0 ? 100 : $target / $value * 100));
    $firstTarget = $kpiTargets['calls_per_day'];
@endphp

@if ($availability === 'Lazy')
    <div class="flex items-start gap-2.5 rounded-lg border border-pedago-red-line bg-pedago-red-tint px-4 py-3 text-sm text-pedago-red" role="alert">
        <x-ui.icon name="alert" class="mt-0.5 size-4" />
        <p><span class="font-semibold">You are flagged Lazy.</span> A follow-up of yours is badly overdue, so you receive no new leads until you handle it. <a class="font-semibold underline underline-offset-2" href="{{ route('follow-ups.mine') }}">Open my follow-ups</a></p>
    </div>
@endif

<section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-pedago-line bg-white px-4 py-3 sm:px-5" aria-label="My status">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
        <span class="font-semibold">My status today</span>
        <x-ui.pill :status="$availability" />
        <span class="num text-xs text-pedago-muted">{{ $shiftLabel ? 'Shift '.$shiftLabel : 'No shift today' }}</span>
        @if ($leave)
            <a class="text-xs font-medium text-pedago-blue-dark underline underline-offset-2" href="{{ route('leave.index') }}">
                {{ $leave->status === 'Approved' ? 'Leave approved' : 'Leave waiting for a decision' }}: {{ $leave->start_date->format('M j') }}{{ $leave->days > 1 ? ' – '.$leave->end_date->format('M j') : '' }}
            </a>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        <x-ui.button :href="route('sales.tasks')" icon="check-square">Open my tasks</x-ui.button>
        <x-ui.button :href="route('leave.index')" variant="secondary" icon="calendar">Apply for leave</x-ui.button>
    </div>
</section>

<section class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7" aria-label="My work">
    <x-ui.stat label="Current batch" :value="$mine['batch_total'] > 0 ? $mine['batch_done'].'/'.$mine['batch_total'] : '—'" :href="route('sales.tasks')"
        :hint="$mine['batch_total'] > 0 ? ($mine['batch_total'] - $mine['batch_done']).' still to call' : 'No batch right now'" />
    <x-ui.stat label="Calls today" :value="number_format($mine['calls_today'])" :hint="'Target '.$kpiTargets['calls_per_day'].' a day'" info="Calls you have logged today. The target is the daily number an administrator sets in Settings." />
    <x-ui.stat label="Overdue follow-ups" :value="number_format($mine['overdue'])" :href="route('follow-ups.mine')" :alert="$mine['overdue'] > 0" hint="Past their time. Call first" />
    <x-ui.stat label="Follow-ups coming up" :value="number_format($mine['coming_up'])" :href="route('follow-ups.mine', ['tab' => 'soon'])" hint="Within the reminder window" />
    <x-ui.stat label="Open leads" :value="number_format($mine['open_leads'])" :href="route('leads.index')" hint="Still in progress" />
    <x-ui.stat label="Awaiting payment" :value="number_format($mine['awaiting_payment'])" hint="Enrolled, not yet verified" />
    <x-ui.stat label="Converted" :value="number_format($mine['converted'])" hint="All time" />
</section>

<x-ui.card title="My follow-ups" flush>
    <x-slot:meta>
        <x-ui.pill tone="red">{{ $mine['overdue'] }} overdue</x-ui.pill>
        <x-ui.pill tone="blue">{{ $mine['coming_up'] }} coming up</x-ui.pill>
    </x-slot:meta>
    @if ($followUps->isEmpty())
        <x-ui.empty class="border-t border-pedago-line" title="No follow-ups due" icon="clock">Nothing is overdue and nothing is coming up in the next {{ $noticeHours }} {{ Str::plural('hour', $noticeHours) }}.</x-ui.empty>
    @else
        <ul class="divide-y divide-pedago-line border-t border-pedago-line">
            @foreach ($followUps as $followUp)
                @php $late = $followUp->followup_date_time->isPast(); @endphp
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold">{{ $followUp->lead?->student_name ?? 'Lead removed' }}</p>
                        <p class="num text-xs text-pedago-muted">L-{{ $followUp->lead_id }} · {{ $followUp->lead?->contact_number }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <x-ui.pill :tone="$late ? 'red' : 'blue'">{{ $late ? 'Overdue' : 'Coming up' }}</x-ui.pill>
                            <p class="num mt-0.5 text-xs text-pedago-muted">{{ $followUp->followup_date_time->format('D, M j · g:i A') }} · {{ $late ? $followUp->followup_date_time->diffForHumans(null, true).' late' : 'in '.$followUp->followup_date_time->diffForHumans(null, true) }}</p>
                        </div>
                        <x-ui.button :href="route('leads.workspace', $followUp->lead_id)" size="sm" :variant="$late ? 'primary' : 'secondary'">Call</x-ui.button>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="border-t border-pedago-line px-4 py-2.5 text-xs sm:px-5"><a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('follow-ups.mine') }}">See every follow-up</a></div>
    @endif
</x-ui.card>

<x-ui.card title="Call next" flush>
    <x-slot:meta>{{ $callNextTotal }} {{ Str::plural('lead', $callNextTotal) }} in your list · <a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('sales.tasks') }}">See all</a></x-slot:meta>
    @if ($callNext->isEmpty())
        <x-ui.empty class="border-t border-pedago-line" title="Nothing to call right now" icon="inbox">New leads arrive on the next distribution cycle while your status is Available.</x-ui.empty>
    @else
        <ol class="divide-y divide-pedago-line border-t border-pedago-line">
            @foreach ($callNext as $position => $entry)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="num flex size-7 shrink-0 items-center justify-center rounded-full bg-pedago-canvas text-xs font-semibold" aria-hidden="true">{{ $position + 1 }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold">{{ $entry['lead']?->student_name }}</p>
                            <p class="num truncate text-xs text-pedago-muted">L-{{ $entry['lead_id'] }} · {{ $entry['lead']?->contact_number }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span @class(['text-xs', 'font-semibold text-pedago-red' => $entry['tone'] === 'red', 'text-pedago-muted' => $entry['tone'] !== 'red'])>{{ $entry['detail'] }}</span>
                        <x-ui.pill :tone="$entry['tone']">{{ $entry['label'] }}</x-ui.pill>
                        <x-ui.button :href="route('leads.workspace', $entry['lead_id'])" size="sm" :variant="$position === 0 ? 'primary' : 'secondary'">{{ $position === 0 ? 'Call now' : 'Open' }}</x-ui.button>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-ui.card>

<section class="flex flex-col gap-3" aria-labelledby="kpi-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-sm font-bold" id="kpi-heading">My KPIs this month</h2>
        <a class="text-xs font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('performance.index') }}">Full performance</a>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.kpi label="Calls per working day" :value="number_format($kpi['calls_per_day'], 1)" :target="$kpiTargets['calls_per_day']" :grade="$kpi['status']['calls_per_day']" :progress="$bar($kpi['calls_per_day'], $kpiTargets['calls_per_day'])" :hint="$kpi['calls'].' calls'" />
        <x-ui.kpi label="First-call time (median)" :value="$minutes($kpi['first_call_minutes'])" :target="$kpiTargets['first_call_minutes'].' min or less'" :grade="$kpi['status']['first_call_minutes']" :progress="$bar($kpi['first_call_minutes'], $kpiTargets['first_call_minutes'], false)" />
        <x-ui.kpi label="Follow-ups kept on time" :value="$pct($kpi['followup_on_time'])" :target="$kpiTargets['followup_on_time'].'%'" :grade="$kpi['status']['followup_on_time']" :progress="$bar($kpi['followup_on_time'], $kpiTargets['followup_on_time'])" :hint="$kpi['followups_kept'].' of '.$kpi['followups_due']" />
        <x-ui.kpi label="Conversion rate" :value="$pct($kpi['conversion'])" :target="$kpiTargets['conversion'].'%'" :grade="$kpi['status']['conversion']" :progress="$bar($kpi['conversion'], $kpiTargets['conversion'])" :hint="$kpi['converted'].' of '.$kpi['assigned'].' assigned'" />
    </div>
</section>
