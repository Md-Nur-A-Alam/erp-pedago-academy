@props(['title', 'icon' => 'check-circle'])

<div {{ $attributes->class('flex flex-col items-center gap-2 px-4 py-12 text-center') }}>
    <span class="flex size-10 items-center justify-center rounded-full bg-pedago-canvas text-pedago-muted">
        <x-ui.icon :name="$icon" class="size-5" />
    </span>
    <p class="text-sm font-semibold text-pedago-ink">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <p class="max-w-md text-sm text-pedago-muted">{{ $slot }}</p>
    @endif
</div>
