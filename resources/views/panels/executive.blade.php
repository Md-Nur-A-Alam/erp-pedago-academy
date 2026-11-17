<section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-pedago-line bg-white px-4 py-3 sm:px-5" aria-label="My status">
    <div class="flex flex-wrap items-center gap-2.5 text-sm">
        <span class="font-semibold">My status today</span>
        <x-ui.pill :status="$availability" />
        <span class="num text-xs text-pedago-muted">{{ $shiftLabel ? 'Shift '.$shiftLabel : 'No shift today' }}</span>
    </div>
    <x-ui.button :href="route('sales.tasks')" icon="check-square">Open my tasks</x-ui.button>
</section>

<section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" aria-label="My work">
    <x-ui.stat label="Current batch" :value="$metrics['batch_total'] > 0 ? $metrics['batch_done'].'/'.$metrics['batch_total'] : '—'" :href="route('sales.tasks')"
        :hint="$metrics['batch_total'] > 0 ? ($metrics['batch_total'] - $metrics['batch_done']).' still to call' : 'No batch right now'" />
    <x-ui.stat label="Overdue follow-ups" :value="number_format($metrics['overdue'])" :href="route('follow-ups.index')" :alert="$metrics['overdue'] > 0" hint="Call these first" />
    <x-ui.stat label="Due later today" :value="number_format($metrics['due_today'])" :href="route('follow-ups.index', ['tab' => 'today'])" hint="Follow-ups scheduled" />
    <x-ui.stat label="Open leads" :value="number_format($metrics['open_leads'])" :href="route('leads.index')" hint="Still in progress" />
    <x-ui.stat label="Awaiting payment" :value="number_format($metrics['awaiting_payment'])" hint="Enrolled, not yet verified" />
    <x-ui.stat label="Converted" :value="number_format($metrics['converted'])" hint="All time" />
</section>

<div class="grid gap-4 lg:grid-cols-2">
    <x-ui.card title="Next leads to call">
        <ul class="divide-y divide-pedago-line">
            @forelse ($nextLeads as $item)
                <li class="flex items-center justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold">{{ $item->lead?->student_name }}</p>
                        <p class="num truncate text-xs text-pedago-muted">L-{{ $item->lead_id }} · {{ $item->lead?->contact_number }}</p>
                    </div>
                    <x-ui.button :href="route('leads.workspace', $item->lead_id)" variant="secondary" size="sm">Open</x-ui.button>
                </li>
            @empty
                <li class="py-3 text-[13px] text-pedago-muted">Your batch is finished. New leads arrive on the next distribution cycle while you are available.</li>
            @endforelse
        </ul>
    </x-ui.card>

    <x-ui.card title="Next follow-ups">
        <ul class="divide-y divide-pedago-line">
            @forelse ($nextFollowUps as $followUp)
                @php $late = $followUp->followup_date_time->isPast(); @endphp
                <li class="flex items-center justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold">{{ $followUp->lead?->student_name }}</p>
                        <p @class(['truncate text-xs', 'font-semibold text-pedago-red' => $late, 'text-pedago-muted' => ! $late])>{{ $late ? 'Overdue by '.$followUp->followup_date_time->diffForHumans(null, true) : $followUp->followup_date_time->format('M j, g:i A') }}</p>
                    </div>
                    <x-ui.button :href="route('leads.workspace', $followUp->lead_id)" variant="secondary" size="sm">Open</x-ui.button>
                </li>
            @empty
                <li class="py-3 text-[13px] text-pedago-muted">No follow-up is scheduled.</li>
            @endforelse
        </ul>
    </x-ui.card>
</div>
