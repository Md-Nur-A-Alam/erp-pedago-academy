@props(['text' => null, 'term' => null])

@php
    $message = $text ?? ($term !== null ? (config('glossary.terms')[$term] ?? null) : null);
    $tipId = 'tip-'.substr(md5((string) $message), 0, 8).'-'.\Illuminate\Support\Str::random(4);
@endphp

@if ($message)
    <span {{ $attributes->class('group/info relative inline-flex shrink-0 align-middle') }} data-info>
        <button class="inline-flex size-5 items-center justify-center rounded-full text-pedago-muted transition-colors duration-150 hover:text-pedago-blue-dark focus-visible:text-pedago-blue-dark" type="button" aria-label="What does this mean?" aria-describedby="{{ $tipId }}">
            <x-ui.icon name="info" class="size-4" />
        </button>
        <span class="pointer-events-none invisible fixed z-50 w-64 max-w-[calc(100vw-1rem)] rounded-md bg-pedago-ink px-3 py-2 text-left text-xs font-normal normal-case leading-5 tracking-normal text-white opacity-0 shadow-lg transition-opacity duration-150 group-data-[shown=true]/info:visible group-data-[shown=true]/info:opacity-100" id="{{ $tipId }}" role="tooltip">{{ $message }}</span>
    </span>
@endif
