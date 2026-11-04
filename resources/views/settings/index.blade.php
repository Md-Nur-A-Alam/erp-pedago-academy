@extends('layouts.app', ['title' => 'Settings'])

@section('page-title', 'Settings')
@section('page-description', 'Configure defaults used by lead distribution.')
@section('page-actions')
    <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit" form="system-settings-form">Save settings</button>
@endsection

@section('content')
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <form class="flex flex-col gap-5 rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-7" id="system-settings-form" method="POST" action="{{ route('settings.update') }}">
            @csrf
            @method('PUT')
            <section class="flex flex-col gap-4 rounded-lg border border-pedago-line bg-pedago-canvas/50 p-4 sm:grid sm:grid-cols-[minmax(0,1fr)_12rem] sm:items-center sm:p-5">
                <div class="flex flex-col gap-1">
                    <label class="font-semibold" for="lead_batch_size">Default lead batch size</label>
                    <p class="text-sm text-pedago-muted">{{ $settings['lead_batch_size']->description }}</p>
                </div>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="lead_batch_size" name="settings[lead_batch_size]" type="number" min="1" max="50" value="{{ old('settings.lead_batch_size', $settings['lead_batch_size']->value) }}" required>
            </section>

            <section class="flex flex-col gap-4 rounded-lg border border-pedago-line bg-pedago-canvas/50 p-4 sm:grid sm:grid-cols-[minmax(0,1fr)_12rem] sm:items-center sm:p-5">
                <div class="flex flex-col gap-1">
                    <label class="font-semibold" for="assignment_cutoff_minutes">Assignment cutoff</label>
                    <p class="text-sm text-pedago-muted">{{ $settings['assignment_cutoff_minutes']->description }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="assignment_cutoff_minutes" name="settings[assignment_cutoff_minutes]" type="number" min="0" max="180" value="{{ old('settings.assignment_cutoff_minutes', $settings['assignment_cutoff_minutes']->value) }}" required>
                    <span class="text-sm text-pedago-muted">min</span>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-pedago-line pt-5">
                <p class="max-w-xl text-xs leading-5 text-pedago-muted">Settings are stored as application defaults. Changes do not modify leads that have already been assigned.</p>
            </div>
        </form>
    </div>
@endsection
