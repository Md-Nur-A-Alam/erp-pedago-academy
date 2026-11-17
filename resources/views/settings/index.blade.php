@extends('layouts.app', ['title' => 'Settings'])

@section('page-title', 'Settings')
@section('page-description', 'Rules used by lead distribution, follow-up discipline and finance.')
@section('page-actions')
    <x-ui.button type="submit" form="system-settings-form">Save settings</x-ui.button>
@endsection

@section('content')
    <form class="mx-auto flex w-full max-w-4xl flex-col gap-4" id="system-settings-form" method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')

        @foreach ($groups as $group => $definitions)
            <x-ui.card :title="$group" flush>
                <div class="divide-y divide-pedago-line border-t border-pedago-line">
                    @foreach ($definitions as $key => $definition)
                        @php $value = old('settings.'.$key, $settings[$key]->value); @endphp
                        <div class="grid gap-3 px-4 py-4 sm:grid-cols-[minmax(0,1fr)_13rem] sm:items-center sm:px-5">
                            <div>
                                <label class="text-sm font-semibold" for="{{ $key }}">{{ $definition['label'] }}</label>
                                <p class="mt-0.5 text-[13px] leading-5 text-pedago-muted" id="{{ $key }}-help">{{ $definition['description'] }}</p>
                                @error('settings.'.$key)
                                    <p class="mt-1 text-[13px] font-medium text-pedago-red" id="{{ $key }}-error">{{ $message }}</p>
                                @enderror
                            </div>

                            @if ($definition['type'] === 'boolean')
                                <div>
                                    <select class="h-10 w-full rounded-md border border-pedago-line bg-white px-2.5 text-sm" id="{{ $key }}" name="settings[{{ $key }}]" aria-describedby="{{ $key }}-help">
                                        <option value="1" @selected((string) $value === '1')>On</option>
                                        <option value="0" @selected((string) $value !== '1')>Off</option>
                                    </select>
                                </div>
                            @elseif ($definition['type'] === 'text')
                                <div>
                                    <textarea class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm" id="{{ $key }}" name="settings[{{ $key }}]" rows="4" maxlength="{{ $definition['max'] }}" required aria-describedby="{{ $key }}-help">{{ $value }}</textarea>
                                    <p class="mt-1 text-xs text-pedago-muted">Up to {{ $definition['max'] }} characters</p>
                                </div>
                            @else
                                <div>
                                    <div class="flex items-center gap-2">
                                        <input @class(['num h-10 w-full rounded-md border bg-white px-3 text-sm', 'border-pedago-red' => $errors->has('settings.'.$key), 'border-pedago-line' => ! $errors->has('settings.'.$key)])
                                            id="{{ $key }}" name="settings[{{ $key }}]" type="number" inputmode="numeric" min="{{ $definition['min'] }}" max="{{ $definition['max'] }}" value="{{ $value }}" required
                                            aria-describedby="{{ $key }}-help" @error('settings.'.$key) aria-invalid="true" @enderror>
                                        <span class="w-12 shrink-0 text-[13px] text-pedago-muted">{{ $definition['unit'] ?? '' }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-pedago-muted">{{ $definition['min'] }} to {{ $definition['max'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-xl text-xs leading-5 text-pedago-muted">Changes apply from the next distribution cycle and do not alter leads that are already assigned.</p>
            <x-ui.button type="submit">Save settings</x-ui.button>
        </div>
    </form>
@endsection
