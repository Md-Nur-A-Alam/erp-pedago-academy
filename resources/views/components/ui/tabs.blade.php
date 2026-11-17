@props(['tabs', 'current', 'route', 'param' => 'tab', 'counts' => [], 'query' => [], 'label' => 'Views'])

<nav class="flex gap-1 overflow-x-auto border-b border-pedago-line" aria-label="{{ $label }}">
    @foreach ($tabs as $key => $name)
        @php $tabKey = is_int($key) ? $name : $key; @endphp
        @php $active = $current === $tabKey; @endphp
        <a @class([
            '-mb-px inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition-colors duration-150',
            'border-pedago-blue text-pedago-blue-dark' => $active,
            'border-transparent text-pedago-muted hover:border-pedago-line hover:text-pedago-ink' => ! $active,
        ]) href="{{ route($route, array_merge($query, [$param => $tabKey])) }}" @if ($active) aria-current="page" @endif>
            {{ $name }}
            @if (array_key_exists($tabKey, is_array($counts) ? $counts : $counts->all()))
                <span @class(['num rounded-full px-1.5 text-xs font-medium', 'bg-pedago-tint text-pedago-blue-dark' => $active, 'bg-pedago-canvas text-pedago-ink' => ! $active])>{{ number_format($counts[$tabKey]) }}</span>
            @endif
        </a>
    @endforeach
</nav>
