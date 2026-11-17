@extends('layouts.app', ['title' => 'Merge duplicates'])

@section('page-title', 'Merge duplicates')
@section('page-description', 'Leads that share a phone number. Merge them so the history stays in one place.')

@section('content')
    @if ($groups->isEmpty())
        <x-ui.card><x-ui.empty title="No duplicate leads" icon="copy">Every lead has its own phone number.</x-ui.empty></x-ui.card>
    @else
        @foreach ($groups as $leads)
            @php $keep = $leads->first(); @endphp
            <x-ui.card :title="'Number '.$leads->first()->contact_number">
                <x-slot:meta>{{ $leads->count() }} leads</x-slot:meta>
                <ul class="divide-y divide-pedago-line">
                    @foreach ($leads as $lead)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2 text-[13px]">
                            <span class="min-w-0">
                                <a class="num font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('leads.workspace', $lead) }}">L-{{ $lead->id }}</a>
                                <span class="font-medium">{{ $lead->student_name }}</span>
                                <span class="block text-xs text-pedago-muted">{{ $lead->currentOwner?->name ?? 'No owner' }} · received {{ $lead->created_at?->format('M j, Y') }}</span>
                            </span>
                            <span class="flex items-center gap-3">
                                <x-ui.pill :status="$lead->status" />
                                @if ($lead->id !== $keep->id)
                                    <form method="POST" action="{{ route('leads.merge.store') }}" data-confirm="Merge L-{{ $lead->id }} into L-{{ $keep->id }}? Its calls and numbers move to L-{{ $keep->id }} and L-{{ $lead->id }} is removed.">
                                        @csrf
                                        <input type="hidden" name="keep_id" value="{{ $keep->id }}">
                                        <input type="hidden" name="duplicate_id" value="{{ $lead->id }}">
                                        <x-ui.button type="submit" variant="secondary" size="sm">Merge into L-{{ $keep->id }}</x-ui.button>
                                    </form>
                                @else
                                    <span class="text-xs font-semibold text-pedago-green">Oldest, kept</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endforeach
    @endif
@endsection
