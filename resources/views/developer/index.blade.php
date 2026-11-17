@extends('layouts.app', ['title' => 'Developer panel'])

@section('page-title', 'Developer panel')
@section('page-description', 'Open the system as any employee, check the environment and run maintenance tools.')

@section('page-actions')
    <x-ui.button :href="route('developer.guide')" variant="secondary" icon="code">Developer guide</x-ui.button>
@endsection

@section('content')
    @php
        $roleLabels = ['Admin' => 'Administrator', 'Supervisor' => 'Supervisor', 'Team Leader' => 'Team leader', 'Sales Executive' => 'Sales executive', 'MarketingExecutive' => 'Marketer', 'Accounts' => 'Accounts'];
    @endphp

    <div class="flex items-start gap-2.5 rounded-lg border border-pedago-amber/30 bg-pedago-amber-tint px-4 py-3 text-sm text-pedago-amber" role="note">
        <x-ui.icon name="alert" class="mt-0.5 size-4" />
        <p>Opening a panel signs you in as that employee. Everything you do there is real and is saved under their name. Every switch is recorded below. Use a test database when you can.</p>
    </div>

    <x-ui.card title="Switch panel" flush>
        <x-slot:meta>One click opens the system exactly as that person sees it</x-slot:meta>

        <form class="flex flex-wrap items-end gap-2 border-t border-pedago-line px-4 py-3 sm:px-5" method="GET" action="{{ route('developer.index') }}" role="search">
            <div class="w-full sm:w-64">
                <label class="mb-1 block text-xs font-medium text-pedago-muted" for="developer-search">Find an employee</label>
                <input class="h-9 w-full rounded-md border border-pedago-line px-2.5 text-sm" id="developer-search" name="search" type="search" value="{{ $search }}" placeholder="Name or office ID">
            </div>
            <x-ui.button type="submit" variant="secondary">Search</x-ui.button>
            @if ($search !== '')
                <a class="inline-flex min-h-9 items-center px-2 text-sm font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('developer.index') }}">Clear</a>
            @endif
        </form>

        @if ($byRole->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No employee to open" icon="users">Create employees under another role first, or clear the search.</x-ui.empty>
        @else
            <div class="grid gap-px border-t border-pedago-line bg-pedago-line md:grid-cols-2 xl:grid-cols-3">
                @foreach ($roleLabels as $roleKey => $roleLabel)
                    @continue(! $byRole->has($roleKey))
                    <section class="bg-white px-4 py-3 sm:px-5" aria-label="{{ $roleLabel }} accounts">
                        <h3 class="mb-2 flex items-center justify-between text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">
                            {{ $roleLabel }} <span class="num font-medium normal-case tracking-normal">{{ $byRole[$roleKey]->count() }}</span>
                        </h3>
                        <ul class="flex flex-col gap-1.5">
                            @foreach ($byRole[$roleKey] as $person)
                                @php
                                    $team = $person->team?->name ?? $person->teamsLed->first()?->name;
                                    $notes = array_filter([
                                        $person->status !== 'Active' ? $person->status : null,
                                        $person->user?->must_change_password ? 'temporary password' : null,
                                        ! $person->isProfileComplete() ? 'profile incomplete' : null,
                                    ]);
                                @endphp
                                <li class="flex items-center justify-between gap-3 rounded-md border border-pedago-line px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-semibold">{{ $person->name }}</p>
                                        <p class="truncate text-xs text-pedago-muted"><span class="num">{{ $person->office_id }}</span>{{ $team ? ' · '.$team : '' }}{{ $notes ? ' · '.implode(', ', $notes) : '' }}</p>
                                    </div>
                                    @if ($person->status === 'Active')
                                        <form method="POST" action="{{ route('developer.view-as', $person) }}">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" icon="eye" aria-label="Open the system as {{ $person->name }}">Open</x-ui.button>
                                        </form>
                                    @else
                                        <x-ui.pill tone="slate">{{ $person->status }}</x-ui.pill>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <x-ui.card title="Environment" flush>
            <dl class="grid grid-cols-[9rem_minmax(0,1fr)] border-t border-pedago-line text-[13px]">
                @foreach ($environment as $term => $value)
                    <dt class="border-b border-pedago-line px-4 py-2 text-pedago-muted sm:pl-5">{{ $term }}</dt>
                    <dd class="num break-all border-b border-pedago-line px-2 py-2 font-medium">{{ $value }}</dd>
                @endforeach
            </dl>
            <div class="px-4 py-3 sm:px-5">
                <h3 class="text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Scheduled commands</h3>
                <ul class="mt-1 divide-y divide-pedago-line text-[13px]">
                    @forelse ($schedule as $event)
                        <li class="flex flex-wrap justify-between gap-2 py-1.5"><span class="num">{{ $event['command'] }}</span><span class="text-xs text-pedago-muted"><span class="num">{{ $event['expression'] }}</span> · next {{ $event['next'] }}</span></li>
                    @empty
                        <li class="py-1.5 text-pedago-muted">None.</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-pedago-muted">These run only while <span class="num">php artisan schedule:work</span> or a cron entry is active.</p>
            </div>
        </x-ui.card>

        <div class="flex flex-col gap-4">
            <x-ui.card title="Tools">
                <ul class="flex flex-col gap-2">
                    @foreach ($tools as $tool => $label)
                        <li class="flex items-center justify-between gap-3">
                            <span class="text-[13px] font-medium">{{ $label }}</span>
                            <form method="POST" action="{{ route('developer.tools', $tool) }}" data-confirm="{{ $label }} now?">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm">Run</x-ui.button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs leading-5 text-pedago-muted">The distribution cycle runs even when the engine switch is off. All tools act on the current database.</p>
            </x-ui.card>

            <x-ui.card title="Record counts" flush>
                <ul class="grid grid-cols-2 border-t border-pedago-line text-[13px]">
                    @foreach ($counts as $table => $count)
                        <li class="flex justify-between gap-2 border-b border-pedago-line px-4 py-1.5 odd:border-r sm:px-5"><span class="num truncate text-pedago-muted">{{ $table }}</span><span class="num font-semibold">{{ number_format($count) }}</span></li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>

    <x-ui.card title="Developer activity" flush>
        <x-slot:meta>Last {{ $logs->count() }} entries</x-slot:meta>
        @if ($logs->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No developer activity yet" icon="terminal" />
        @else
            <ul class="divide-y divide-pedago-line border-t border-pedago-line">
                @foreach ($logs as $log)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-2.5 text-[13px] sm:px-5">
                        <p><span class="font-semibold">{{ $log->action }}</span>@if ($log->target) <span class="text-pedago-muted">—</span> {{ $log->target->name }} ({{ $log->target->role }})@elseif ($log->details) <span class="text-pedago-muted">— {{ $log->details }}</span>@endif</p>
                        <p class="num text-xs text-pedago-muted">{{ $log->created_at?->format('M j, Y g:i A') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection
