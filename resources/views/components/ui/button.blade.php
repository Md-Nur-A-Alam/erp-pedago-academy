@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'icon' => null])

@php
    $variants = [
        'primary' => 'border-pedago-blue bg-pedago-blue text-white hover:bg-pedago-blue-dark',
        'secondary' => 'border-pedago-line bg-white text-pedago-blue-dark hover:border-pedago-blue hover:bg-pedago-tint',
        'danger' => 'border-pedago-red-line bg-white text-pedago-red hover:bg-pedago-red-tint',
        'success' => 'border-pedago-green bg-pedago-green text-white hover:brightness-110',
    ];
    $sizes = ['md' => 'min-h-9 px-3.5 text-sm', 'sm' => 'min-h-8 px-2.5 text-xs'];
    $classes = 'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-md border font-semibold transition-colors duration-150 disabled:opacity-50 '
        .$variants[$variant].' '.$sizes[$size];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>@if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif{{ $slot }}</button>
@endif
