@props(['range', 'start', 'end', 'route'])

<form class="flex flex-wrap items-center gap-1.5" method="GET" action="{{ route($route) }}" aria-label="Reporting period">
    @foreach (['today' => 'Today', 'this_month' => 'This month', 'three_months' => '3 months', 'this_year' => 'This year'] as $preset => $label)
        <button @class([
            'min-h-9 rounded-md border px-3 text-sm font-semibold transition-colors duration-150',
            'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' => $range === $preset,
            'border-pedago-line bg-white text-pedago-ink hover:bg-pedago-canvas' => $range !== $preset,
        ]) type="submit" name="range" value="{{ $preset }}" aria-pressed="{{ $range === $preset ? 'true' : 'false' }}">{{ $label }}</button>
    @endforeach
    <span class="flex flex-wrap items-center gap-1.5 sm:ml-2">
        <label class="sr-only" for="period-start">Custom start date</label>
        <input class="h-9 w-36 rounded-md border border-pedago-line bg-white px-2 text-sm" id="period-start" name="start_date" type="date" value="{{ $range === 'custom' ? $start->toDateString() : '' }}">
        <span class="text-sm text-pedago-muted" aria-hidden="true">to</span>
        <label class="sr-only" for="period-end">Custom end date</label>
        <input class="h-9 w-36 rounded-md border border-pedago-line bg-white px-2 text-sm" id="period-end" name="end_date" type="date" value="{{ $range === 'custom' ? $end->toDateString() : '' }}">
        <button @class([
            'min-h-9 rounded-md border px-3 text-sm font-semibold transition-colors duration-150',
            'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' => $range === 'custom',
            'border-pedago-line bg-white text-pedago-ink hover:bg-pedago-canvas' => $range !== 'custom',
        ]) type="submit" name="range" value="custom" aria-pressed="{{ $range === 'custom' ? 'true' : 'false' }}">Apply</button>
    </span>
</form>
