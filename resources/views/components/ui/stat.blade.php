@props(['label', 'value', 'hint' => null, 'alert' => false, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'block rounded-lg border bg-white px-4 py-3.5 transition-colors duration-150',
    'border-pedago-red-line' => $alert,
    'border-pedago-line' => ! $alert,
    'hover:border-pedago-blue hover:bg-pedago-row' => $href,
]) }}>
    <span class="block text-xs font-medium text-pedago-muted">{{ $label }}</span>
    <span @class(['num mt-1 block text-[28px] font-semibold leading-tight', 'text-pedago-red' => $alert, 'text-pedago-ink' => ! $alert])>{{ $value }}</span>
    @if ($hint)
        <span class="mt-0.5 block text-xs text-pedago-muted">{{ $hint }}</span>
    @endif
</{{ $tag }}>
