@props(['id', 'title'])

<dialog {{ $attributes->class('m-auto w-[min(32rem,calc(100vw-2rem))] rounded-lg border border-pedago-line bg-white p-0 text-pedago-ink') }} id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <div class="flex items-start justify-between gap-3 border-b border-pedago-line px-5 py-3.5">
        <div class="min-w-0">
            <h2 class="text-base font-bold" id="{{ $id }}-title">{{ $title }}</h2>
            <p class="truncate text-xs text-pedago-muted" data-dialog-subject></p>
        </div>
        <button class="-mr-2 -mt-1 inline-flex size-10 shrink-0 items-center justify-center rounded-md text-pedago-muted transition-colors duration-150 hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-dialog-close aria-label="Close dialog">
            <x-ui.icon name="x" />
        </button>
    </div>
    <div class="px-5 py-4">
        {{ $slot }}
    </div>
</dialog>
