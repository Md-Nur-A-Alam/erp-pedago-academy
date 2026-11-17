@extends('layouts.app', ['title' => 'My tasks'])

@section('page-title', 'My tasks')
@section('page-description', 'One list, in the order that wins the most enrollments.')

@section('content')
    @php
        $open = $items->where('is_completed', false);
        $done = $items->count() - $open->count();
    @endphp

    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-pedago-line bg-white px-4 py-3 text-sm sm:px-5" aria-label="My status">
        <span class="font-semibold">Status</span>
        <x-ui.pill :status="$availability" />
        @if ($items->isNotEmpty())
            <span class="flex items-center gap-2">
                <span class="inline-block h-2 w-28 overflow-hidden rounded-full bg-pedago-line" aria-hidden="true"><span class="block h-full bg-pedago-blue" style="width: {{ (int) round($done / $items->count() * 100) }}%"></span></span>
                <span class="num text-xs">{{ $done }}/{{ $items->count() }}</span><span class="text-xs text-pedago-muted">batch leads done</span>
            </span>
        @endif
    </section>

    <x-ui.card title="Call next" flush>
        <x-slot:meta>Hot leads first, then promised call-backs, then the rest by freshness · <a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('follow-ups.index') }}">All my follow-ups</a></x-slot:meta>
        @if ($callNext->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="Nothing to call right now" icon="inbox">
                New leads are assigned on the next distribution cycle while your status is Available and no follow-up is badly overdue.
            </x-ui.empty>
        @else
            <ol class="divide-y divide-pedago-line border-t border-pedago-line">
                @foreach ($callNext as $position => $entry)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="num flex size-7 shrink-0 items-center justify-center rounded-full bg-pedago-canvas text-xs font-semibold" aria-hidden="true">{{ $position + 1 }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $entry['lead']?->student_name }}</p>
                                <p class="num text-xs text-pedago-muted">L-{{ $entry['lead_id'] }} · {{ $entry['lead']?->contact_number }}</p>
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

    @if ($done > 0)
        <x-ui.card title="Done in this batch" flush>
            <ul class="divide-y divide-pedago-line border-t border-pedago-line">
                @foreach ($items->where('is_completed', true) as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $item->lead?->student_name }}</p>
                            <p class="num text-xs text-pedago-muted">L-{{ $item->lead_id }} · {{ $item->lead?->contact_number }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.pill tone="green">{{ $item->action_taken }}</x-ui.pill>
                            <x-ui.button :href="route('leads.workspace', $item->lead_id)" variant="secondary" size="sm">View</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($awaitingPayment->isNotEmpty())
        <x-ui.card title="Awaiting payment" flush>
            <x-slot:meta>Converted once Accounts verifies the payment</x-slot:meta>
            <ul class="divide-y divide-pedago-line border-t border-pedago-line">
                @foreach ($awaitingPayment as $lead)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $lead->student_name }}</p>
                            <p class="num text-xs text-pedago-muted">L-{{ $lead->id }} · {{ $lead->contact_number }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.pill :status="$lead->status" />
                            <x-ui.button :href="route('leads.workspace', $lead)" variant="secondary" size="sm">View</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
@endsection
