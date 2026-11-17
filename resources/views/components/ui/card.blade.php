@props(['title' => null, 'meta' => null, 'flush' => false, 'id' => null])

@php $headingId = $id ?? ($title ? 'card-'.\Illuminate\Support\Str::slug($title) : null); @endphp

<section {{ $attributes->class(['rounded-lg border border-pedago-line bg-white', 'p-4 sm:p-5' => ! $flush, 'overflow-hidden' => $flush]) }} @if ($headingId) aria-labelledby="{{ $headingId }}" @endif>
    @if ($title)
        <div @class(['flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1', 'mb-3' => ! $flush, 'px-4 py-3.5 sm:px-5' => $flush])>
            <h2 class="scroll-mt-24 text-sm font-bold text-pedago-ink" id="{{ $headingId }}">{{ $title }}</h2>
            @if ($meta)
                <div class="text-xs text-pedago-muted">{{ $meta }}</div>
            @endif
        </div>
    @endif
    {{ $slot }}
</section>
