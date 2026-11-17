@props(['label', 'value', 'target' => null, 'grade' => null, 'progress' => null, 'hint' => null])

@php
    $tones = ['met' => ['green', 'Target met'], 'near' => ['amber', 'Close'], 'below' => ['red', 'Below target']];
    [$tone, $words] = $tones[$grade] ?? ['slate', 'No data yet'];
    $bar = ['green' => 'bg-pedago-green', 'amber' => 'bg-pedago-amber', 'red' => 'bg-pedago-red', 'slate' => 'bg-pedago-blue-soft'][$tone];
@endphp

<div {{ $attributes->class('rounded-lg border border-pedago-line bg-white px-4 py-3.5') }}>
    <div class="flex items-start justify-between gap-2">
        <span class="flex items-center gap-1 text-xs font-medium text-pedago-muted">{{ $label }}<x-ui.info :term="$label" /></span>
        <x-ui.pill :tone="$tone">{{ $words }}</x-ui.pill>
    </div>
    <span class="num mt-1 block text-[26px] font-semibold leading-tight">{{ $value }}</span>
    @if ($progress !== null)
        <span class="mt-2 block h-1.5 overflow-hidden rounded-full bg-pedago-canvas" aria-hidden="true"><span class="block h-full rounded-full {{ $bar }}" style="width: {{ max(2, min(100, (int) round($progress))) }}%"></span></span>
    @endif
    <span class="mt-1.5 block text-xs text-pedago-muted">@if ($target)Target {{ $target }}@endif{{ $target && $hint ? ' · ' : '' }}{{ $hint }}</span>
</div>
