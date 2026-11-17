@extends('layouts.app', ['title' => 'Number check'])

@section('page-title', 'Number check')
@section('page-description', 'Find out whether a phone number is already a lead, before you add it.')

@section('content')
    <x-ui.card>
        <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('number-check.index') }}" role="search">
            <div class="min-w-0 flex-1 sm:max-w-sm">
                <label class="mb-1 block text-sm font-semibold" for="phone">Phone number</label>
                <input class="h-10 w-full rounded-md border border-pedago-line bg-white px-2.5 text-sm" id="phone" name="phone" type="tel" inputmode="tel" placeholder="01XXXXXXXXX" value="{{ $input }}" autofocus required>
            </div>
            <x-ui.button type="submit" icon="search">Check</x-ui.button>
        </form>
        <p class="mt-2 text-xs text-pedago-muted">It works with +880, 880 or a missing leading zero, and finds the number whether it is the main, WhatsApp, other or an extra number of a lead.</p>
    </x-ui.card>

    @if ($result !== null)
        <x-ui.card title="Result">
            @if (! $result['valid'])
                <p class="text-sm font-medium text-pedago-red">That is not a valid phone number. Use 01XXXXXXXXX, or + and the country code for a number abroad.</p>
            @elseif ($result['lead'] === null)
                <p class="text-sm"><span class="num font-semibold">{{ $result['number'] }}</span> is <span class="font-semibold text-pedago-green">not in the system</span>. It is safe to add as a new lead.</p>
            @else
                @php $lead = $result['lead']; @endphp
                <p class="text-sm"><span class="num font-semibold">{{ $result['number'] }}</span> already belongs to a lead{{ $result['matchedAs'] !== 'primary' ? ', as its '.['whatsapp' => 'WhatsApp', 'alternative' => 'other', 'extra' => 'extra'][$result['matchedAs']].' number' : '' }}.</p>
                <dl class="mt-3 grid max-w-xl grid-cols-[8rem_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-[13px]">
                    <dt class="text-pedago-muted">Lead</dt><dd class="num font-medium">L-{{ $lead->id }} · {{ $lead->student_name }}</dd>
                    <dt class="text-pedago-muted">Status</dt><dd><x-ui.pill :status="$lead->status" /></dd>
                    <dt class="text-pedago-muted">Owner</dt><dd class="font-medium">{{ $lead->currentOwner?->name ?? 'Not assigned yet' }}</dd>
                    <dt class="text-pedago-muted">Campaign</dt><dd class="font-medium">{{ $lead->source?->campaign_name ?? ($lead->source ? \App\Models\LeadSource::CHANNELS[$lead->source->channel] ?? $lead->source->channel : '—') }}</dd>
                    <dt class="text-pedago-muted">Received</dt><dd class="font-medium">{{ $lead->created_at?->format('M j, Y') }}</dd>
                </dl>
                <p class="mt-3 text-xs text-pedago-muted">Do not add it again. Ask the owner, or add this number as a repeat inquiry through the normal upload so it is logged on the same lead.</p>
            @endif
        </x-ui.card>
    @endif
@endsection
