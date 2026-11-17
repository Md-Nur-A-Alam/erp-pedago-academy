@extends('layouts.app', ['title' => 'Data health'])

@section('page-title', 'Data health')
@section('page-description', 'Leads that have quietly got stuck, and one-click fixes for the safe ones.')

@section('content')
    <x-ui.card flush>
        <ul class="divide-y divide-pedago-line">
            @foreach ($checks as $check)
                <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ $check['title'] }}</p>
                        <p class="mt-0.5 max-w-3xl text-[13px] leading-5 text-pedago-muted">{{ $check['detail'] }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-ui.pill :tone="$check['count'] === 0 ? 'green' : 'amber'">{{ $check['count'] === 0 ? 'All good' : $check['count'] }}</x-ui.pill>
                        @if ($check['fix'] && $check['count'] > 0)
                            <form method="POST" action="{{ route('data-health.fix') }}" data-confirm="{{ $check['fix_label'] }} for {{ $check['count'] }} {{ Str::plural('item', $check['count']) }}?">
                                @csrf
                                <input type="hidden" name="fix" value="{{ $check['fix'] }}">
                                <x-ui.button type="submit" size="sm">{{ $check['fix_label'] }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
@endsection
