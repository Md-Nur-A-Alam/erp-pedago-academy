@extends('layouts.app', ['title' => 'Central queue'])

@section('page-title', 'Central queue')
@section('page-description', 'New leads that nobody owns yet, freshest first. Hot and warm leads are distributed before cool and cold ones.')

@section('page-actions')
    <x-ui.button :href="route('leads.upload')" variant="secondary" icon="upload">Upload leads</x-ui.button>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Queue summary">
        <x-ui.stat label="Leads in queue" :value="number_format($queueTotal)"
            :hint="$oldestLead ? 'Oldest waiting '.$oldestLead->created_at->diffForHumans(null, true) : 'Queue is empty'" />
        <x-ui.stat label="Auto-distributed" :value="number_format($autoTotal)" hint="Taken by the engine" />
        <x-ui.stat label="Manual campaigns" :value="number_format($manualTotal)" hint="Assigned by you only" />
        <x-ui.stat label="Available executives" :value="number_format($availableCount)" :hint="'of '.$executives->count().' active sales executives'" />
    </section>

    <x-ui.card title="Next distribution cycle">
        <x-slot:meta>
            <x-ui.pill :tone="$engineEnabled ? 'green' : 'slate'">Automatic: {{ $engineEnabled ? 'on' : 'off' }}</x-ui.pill>
        </x-slot:meta>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
            <div>
                <dl class="grid grid-cols-3 gap-2 lg:grid-cols-1">
                    @foreach (['Queue leads (L)' => number_format($plan['queue']), 'Available (S)' => number_format($plan['executives']), 'Rule' => $plan['rule']] as $term => $figure)
                        <div class="flex flex-col justify-between gap-x-3 rounded-md bg-pedago-canvas px-2.5 py-2 lg:flex-row lg:items-baseline">
                            <dt class="text-xs text-pedago-muted">{{ $term }}</dt>
                            <dd class="num truncate text-base font-semibold">{{ $figure }}</dd>
                        </div>
                    @endforeach
                </dl>
                <form class="mt-3" method="POST" action="{{ route('central-queue.distribute') }}" data-confirm="Assign {{ $plan['assignable'] }} queue {{ Str::plural('lead', $plan['assignable']) }} to {{ count($plan['allocations']) }} {{ Str::plural('executive', count($plan['allocations'])) }} now?">
                    @csrf
                    <x-ui.button class="w-full" type="submit" icon="zap" :disabled="$plan['assignable'] === 0">Run distribution now</x-ui.button>
                </form>
                @if (! $engineEnabled)
                    <p class="mt-2 text-xs leading-5 text-pedago-muted">Automatic distribution is off, so leads move only when you run a cycle or assign them below.</p>
                @endif
            </div>

            <div>
                @if ($plan['allocations'] === [])
                    <div class="flex h-full items-center rounded-md border border-dashed border-pedago-line px-4 py-6">
                        <p class="text-sm text-pedago-muted">{{ $plan['note'] }}</p>
                    </div>
                @else
                    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                        @foreach ($plan['allocations'] as $allocation)
                            <li class="rounded-md border border-pedago-line px-2.5 py-2 text-xs text-pedago-muted">
                                <span class="block truncate text-[13px] font-semibold text-pedago-ink" title="{{ $allocation['employee']->name }}">{{ $allocation['employee']->name }}</span>
                                {{ $allocation['count'] }} {{ Str::plural('lead', $allocation['count']) }}
                                <span class="mt-1 flex flex-wrap gap-1" aria-hidden="true">
                                    @for ($dot = 0; $dot < $allocation['count']; $dot++)
                                        <span @class(['size-2.5 rounded-sm', 'bg-pedago-blue' => $dot < $allocation['base'], 'border-2 border-pedago-red bg-white' => $dot >= $allocation['base']])></span>
                                    @endfor
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-[13px] leading-5 text-pedago-muted">
                        {{ $plan['note'] }}
                        @if ($plan['remaining'] > 0 && $plan['base'] < $plan['batch_size'])
                            {{ $plan['remaining'] }} stay in the queue.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Leads waiting" flush>
        <x-slot:meta>{{ number_format($leads->total()) }} {{ Str::plural('lead', $leads->total()) }}</x-slot:meta>

        <form class="flex flex-wrap items-end gap-2 border-t border-pedago-line px-4 py-3 sm:px-5" method="GET" action="{{ route('central-queue.index') }}" aria-label="Filter the queue">
            <div class="w-full sm:w-56">
                <label class="mb-1 block text-xs font-medium text-pedago-muted" for="queue-search">Search</label>
                <input class="h-9 w-full rounded-md border border-pedago-line px-2.5 text-sm" id="queue-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Phone, name or lead code">
            </div>
            <div class="min-w-40 flex-1 sm:max-w-64">
                <label class="mb-1 block text-xs font-medium text-pedago-muted" for="queue-campaign">Campaign</label>
                <select class="h-9 w-full rounded-md border border-pedago-line bg-white px-2 text-sm" id="queue-campaign" name="source_id">
                    <option value="">All campaigns</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->source_id }}" @selected((string) ($filters['source_id'] ?? '') === (string) $campaign->source_id)>{{ $campaign->campaign_name ?: \App\Models\LeadSource::CHANNELS[$campaign->channel] ?? $campaign->channel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="mb-1 block text-xs font-medium text-pedago-muted" for="queue-mode">Distribution</label>
                <select class="h-9 w-full rounded-md border border-pedago-line bg-white px-2 text-sm" id="queue-mode" name="mode">
                    <option value="">Any</option>
                    @foreach (\App\Models\LeadSource::DISTRIBUTION_MODES as $mode)
                        <option value="{{ $mode }}" @selected(($filters['mode'] ?? '') === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
            @if (array_filter($filters))
                <a class="inline-flex min-h-9 items-center px-2 text-sm font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('central-queue.index') }}">Clear</a>
            @endif
        </form>

        @if ($leads->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="{{ array_filter($filters) ? 'No queue lead matches these filters' : 'The queue is empty' }}" icon="inbox">
                {{ array_filter($filters) ? 'Clear the filters to see every waiting lead.' : 'Every new lead has an owner. Uploaded leads appear here until they are assigned.' }}
            </x-ui.empty>
        @else
            <form method="POST" action="{{ route('central-queue.assign') }}" id="queue-assign-form">
                @csrf
                <div class="flex flex-wrap items-end gap-2 border-t border-pedago-line bg-pedago-row px-4 py-3 sm:px-5">
                    <div class="min-w-52 flex-1 sm:max-w-80">
                        <label class="mb-1 block text-xs font-medium text-pedago-muted" for="queue-executive">Assign selected leads to</label>
                        <select class="h-9 w-full rounded-md border border-pedago-line bg-white px-2 text-sm" id="queue-executive" name="employee_id" required>
                            <option value="">Choose a sales executive</option>
                            @foreach ($executives->groupBy(fn ($executive) => $executive->availability === 'Available' ? 'Available now' : 'Not available now') as $group => $members)
                                <optgroup label="{{ $group }}">
                                    @foreach ($members as $executive)
                                        <option value="{{ $executive->id }}" @selected((string) old('employee_id') === (string) $executive->id)>{{ $executive->name }} ({{ $executive->office_id }}){{ $executive->availability === 'Available' ? '' : ' — '.$executive->availability }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <x-ui.button type="submit" icon="check-square">Assign selected</x-ui.button>
                    <p class="text-xs text-pedago-muted" aria-live="polite"><span class="num font-semibold text-pedago-ink" id="queue-selected-count">0</span> selected</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse text-left">
                        <thead>
                            <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                <th class="w-12 px-4 py-2 sm:pl-5" scope="col">
                                    <input class="size-4 rounded border-pedago-line accent-pedago-blue" id="queue-select-all" type="checkbox" aria-label="Select every lead on this page">
                                </th>
                                <th class="px-2 py-2 font-semibold" scope="col">Lead</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Phone</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Campaign</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Course</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Priority</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Distribution</th>
                                <th class="px-4 py-2 font-semibold sm:pr-5" scope="col">Waiting</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px]">
                            @foreach ($leads as $lead)
                                <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                    <td class="px-4 py-2 sm:pl-5">
                                        <input class="size-4 rounded border-pedago-line accent-pedago-blue" type="checkbox" name="lead_ids[]" value="{{ $lead->id }}" aria-label="Select lead L-{{ $lead->id }}, {{ $lead->student_name }}" data-queue-lead @checked(in_array((string) $lead->id, array_map('strval', (array) old('lead_ids', [])), true))>
                                    </td>
                                    <th class="px-2 py-2 font-medium" scope="row">
                                        <span class="num block text-xs text-pedago-muted">L-{{ $lead->id }}</span>
                                        <span class="block max-w-56 truncate">{{ $lead->student_name }}</span>
                                    </th>
                                    <td class="num whitespace-nowrap px-2 py-2 text-xs">{{ $lead->contact_number }}</td>
                                    <td class="px-2 py-2">
                                        <span class="block max-w-52 truncate">{{ $lead->source?->campaign_name ?: '—' }}</span>
                                        <span class="block text-xs text-pedago-muted">{{ \App\Models\LeadSource::CHANNELS[$lead->source?->channel ?? ''] ?? $lead->source?->channel }}</span>
                                    </td>
                                    <td class="px-2 py-2"><span class="block max-w-48 truncate">{{ $lead->interestedCourse?->title ?? '—' }}</span></td>
                                    <td class="px-2 py-2"><x-ui.pill :status="$lead->temperature()" /></td>
                                    <td class="px-2 py-2"><x-ui.pill :status="$lead->source?->distribution_mode ?? 'Auto'" /></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-xs text-pedago-muted sm:pr-5" title="{{ $lead->created_at->format('M j, Y g:i A') }}">{{ $lead->created_at->diffForHumans(null, true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            @if ($leads->hasPages())
                <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $leads->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection

@push('scripts')
    <script>
        (() => {
            const boxes = Array.from(document.querySelectorAll('[data-queue-lead]'));
            const all = document.getElementById('queue-select-all');
            const counter = document.getElementById('queue-selected-count');
            if (!all || !counter) {
                return;
            }
            const refresh = () => {
                const selected = boxes.filter((box) => box.checked).length;
                counter.textContent = String(selected);
                all.checked = selected > 0 && selected === boxes.length;
                all.indeterminate = selected > 0 && selected < boxes.length;
            };
            all.addEventListener('change', () => {
                boxes.forEach((box) => { box.checked = all.checked; });
                refresh();
            });
            boxes.forEach((box) => box.addEventListener('change', refresh));
            refresh();
        })();
    </script>
@endpush
