@props(['label', 'value', 'hint' => null, 'alert' => false, 'href' => null, 'info' => null])

<div {{ $attributes->class([
    'relative block rounded-lg border bg-white px-4 py-3.5 transition-colors duration-150',
    'border-pedago-red-line' => $alert,
    'border-pedago-line' => ! $alert,
    'hover:border-pedago-blue hover:bg-pedago-row' => $href,
]) }}>
    @if ($href)
        <a class="absolute inset-0 rounded-lg" href="{{ $href }}"><span class="sr-only">Open {{ $label }}</span></a>
    @endif
    <span class="flex items-center gap-1 text-xs font-medium text-pedago-muted">
        {{ $label }}
        <x-ui.info class="relative z-10" :text="$info" :term="$label" />
    </span>
    <span @class(['num mt-1 block text-[28px] font-semibold leading-tight', 'text-pedago-red' => $alert, 'text-pedago-ink' => ! $alert])>{{ $value }}</span>
    @if ($hint)
        <span class="mt-0.5 block text-xs text-pedago-muted">{{ $hint }}</span>
    @endif
</div>
