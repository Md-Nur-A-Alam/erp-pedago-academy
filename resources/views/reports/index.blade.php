@extends('layouts.app', ['title' => 'Reports'])

@section('page-title', 'Reports')
@section('page-description', 'Sales performance for '.$period['label'].'.')

@section('content')
    @php
        $taka = fn (float $amount): string => '৳ '.number_format($amount);
        $rate = fn (int $part, int $whole): string => $whole > 0 ? number_format($part / $whole * 100, 1).'%' : '—';
        $maxPipeline = max(1, (int) $pipeline->max());
        $maxCampaign = max(1, (int) $campaigns->max('leads_count'));
    @endphp

    <form class="flex flex-wrap items-center gap-1.5" method="GET" action="{{ route('reports.index') }}" aria-label="Reporting period">
        @foreach (['today' => 'Today', 'this_month' => 'This month', 'three_months' => '3 months', 'this_year' => 'This year'] as $preset => $label)
            <button @class([
                'min-h-9 rounded-md border px-3 text-sm font-semibold transition-colors duration-150',
                'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' => $period['range'] === $preset,
                'border-pedago-line bg-white text-pedago-ink hover:bg-pedago-canvas' => $period['range'] !== $preset,
            ]) type="submit" name="range" value="{{ $preset }}" aria-pressed="{{ $period['range'] === $preset ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
        <span class="flex flex-wrap items-center gap-1.5 sm:ml-2">
            <label class="sr-only" for="report-start-date">Custom start date</label>
            <input class="h-9 w-36 rounded-md border border-pedago-line bg-white px-2 text-sm" id="report-start-date" name="start_date" type="date" value="{{ $period['range'] === 'custom' ? $period['start']->toDateString() : '' }}">
            <span class="text-sm text-pedago-muted" aria-hidden="true">to</span>
            <label class="sr-only" for="report-end-date">Custom end date</label>
            <input class="h-9 w-36 rounded-md border border-pedago-line bg-white px-2 text-sm" id="report-end-date" name="end_date" type="date" value="{{ $period['range'] === 'custom' ? $period['end']->toDateString() : '' }}">
            <button @class([
                'min-h-9 rounded-md border px-3 text-sm font-semibold transition-colors duration-150',
                'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' => $period['range'] === 'custom',
                'border-pedago-line bg-white text-pedago-ink hover:bg-pedago-canvas' => $period['range'] !== 'custom',
            ]) type="submit" name="range" value="custom" aria-pressed="{{ $period['range'] === 'custom' ? 'true' : 'false' }}">Apply</button>
        </span>
    </form>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Summary">
        <x-ui.stat label="Leads received" :value="number_format($summary['total_leads'])" hint="Created in this period" />
        <x-ui.stat label="Assigned" :value="number_format($summary['assigned_leads'])" :hint="$rate($summary['assigned_leads'], $summary['total_leads']).' of leads received'" />
        <x-ui.stat label="Converted" :value="number_format($summary['converted_leads'])" hint="Of the leads received" />
        <x-ui.stat label="Conversion rate" :value="$summary['total_leads'] > 0 ? number_format($summary['conversion_rate'], 1).'%' : '—'" hint="Converted ÷ received" />
        <x-ui.stat class="col-span-2 lg:col-span-1" label="Collected" :value="$taka($summary['collected'])" hint="Payments verified in this period" />
    </section>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <x-ui.card title="Leads by status">
            <x-slot:meta>Leads received in this period</x-slot:meta>
            @if ($pipeline->isEmpty())
                <x-ui.empty title="No leads in this period" icon="inbox">Choose a wider period to see the pipeline.</x-ui.empty>
            @else
                <ul class="flex flex-col gap-1.5">
                    @foreach (\App\Http\Controllers\DashboardController::PIPELINE_ORDER as $status)
                        @continue(! $pipeline->has($status))
                        @php $count = (int) $pipeline->get($status); @endphp
                        <li class="grid grid-cols-[8.5rem_minmax(0,1fr)_5.5rem] items-center gap-2.5 text-[13px]">
                            <span class="truncate" title="{{ $status }}">{{ $status }}</span>
                            <span class="block h-3.5 rounded-sm bg-pedago-canvas">
                                <span @class(['block h-full rounded-sm', 'bg-pedago-green' => $status === 'Converted', 'bg-pedago-blue' => $status !== 'Converted']) style="width: {{ max(2, (int) round($count / $maxPipeline * 100)) }}%"></span>
                            </span>
                            <span class="num text-right text-xs"><span class="font-semibold">{{ number_format($count) }}</span> <span class="text-pedago-muted">{{ $rate($count, $summary['total_leads']) }}</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Campaigns" flush>
            <x-slot:meta>Leads received and converted, by campaign</x-slot:meta>
            @if ($campaigns->isEmpty())
                <x-ui.empty class="border-t border-pedago-line" title="No campaign brought leads in this period" icon="flag" />
            @else
                <div class="max-h-[26rem] overflow-auto">
                    <table class="w-full min-w-[520px] border-collapse text-left">
                        <thead class="sticky top-0">
                            <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Campaign</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Leads</th>
                                <th class="px-2 py-2 text-right font-semibold" scope="col">Converted</th>
                                <th class="px-4 py-2 text-right font-semibold sm:pr-5" scope="col">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px]">
                            @foreach ($campaigns as $campaign)
                                <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                    <th class="px-4 py-2 font-medium sm:pl-5" scope="row">
                                        <span class="block max-w-56 truncate">{{ $campaign->campaign_name ?: 'Unnamed campaign' }}</span>
                                        <span class="block text-xs font-normal text-pedago-muted">{{ \App\Models\LeadSource::CHANNELS[$campaign->channel] ?? $campaign->channel }}</span>
                                    </th>
                                    <td class="px-2 py-2">
                                        <span class="flex items-center gap-2">
                                            <span class="block h-2.5 w-24 rounded-sm bg-pedago-canvas" aria-hidden="true"><span class="block h-full rounded-sm bg-pedago-blue" style="width: {{ max(3, (int) round($campaign->leads_count / $maxCampaign * 100)) }}%"></span></span>
                                            <span class="num text-xs font-semibold">{{ number_format($campaign->leads_count) }}</span>
                                        </span>
                                    </td>
                                    <td class="num px-2 py-2 text-right text-xs">{{ number_format($campaign->converted_count) }}</td>
                                    <td class="num px-4 py-2 text-right text-xs font-semibold sm:pr-5">{{ $rate($campaign->converted_count, $campaign->leads_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Sales executives" flush>
        <x-slot:meta>Leads assigned in this period and what became of them</x-slot:meta>
        @if ($executives->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No sales executives yet" icon="users" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Executive</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Team</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Assigned</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Calls logged</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Converted</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Rate</th>
                            <th class="px-4 py-2 text-right font-semibold sm:pr-5" scope="col">Overdue now</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($executives as $executive)
                            <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                <th class="px-4 py-2 font-medium sm:pl-5" scope="row">
                                    <span class="block max-w-52 truncate">{{ $executive->name }}</span>
                                    @if ($executive->status !== 'Active')
                                        <span class="block text-xs font-normal text-pedago-muted">{{ $executive->status }}</span>
                                    @endif
                                </th>
                                <td class="px-2 py-2"><span class="block max-w-40 truncate">{{ $executive->team?->name ?? '—' }}</span></td>
                                <td class="num px-2 py-2 text-right text-xs">{{ number_format($executive->assigned_count) }}</td>
                                <td class="num px-2 py-2 text-right text-xs">{{ number_format($executive->calls_count) }}</td>
                                <td class="num px-2 py-2 text-right text-xs font-semibold">{{ number_format($executive->converted_count) }}</td>
                                <td class="num px-2 py-2 text-right text-xs">{{ $rate($executive->converted_count, $executive->assigned_count) }}</td>
                                <td @class(['num px-4 py-2 text-right text-xs sm:pr-5', 'font-semibold text-pedago-red' => $executive->overdue_count > 0])>{{ number_format($executive->overdue_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Teams" flush>
        <x-slot:meta>Leads assigned to each team in this period</x-slot:meta>
        @if ($teams->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No teams yet" icon="users" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] border-collapse text-left">
                    <thead>
                        <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Team</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Active members</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Assigned</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Converted</th>
                            <th class="px-4 py-2 text-right font-semibold sm:pr-5" scope="col">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($teams as $team)
                            <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                <th class="px-4 py-2 font-medium sm:pl-5" scope="row">
                                    <a class="text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('teams.show', $team) }}">{{ $team->name }}</a>
                                    @unless ($team->is_active)
                                        <span class="ml-1 text-xs font-normal text-pedago-muted">Inactive</span>
                                    @endunless
                                </th>
                                <td class="num px-2 py-2 text-right text-xs">{{ number_format($team->members_count) }}</td>
                                <td class="num px-2 py-2 text-right text-xs">{{ number_format($team->assigned_count) }}</td>
                                <td class="num px-2 py-2 text-right text-xs font-semibold">{{ number_format($team->converted_count) }}</td>
                                <td class="num px-4 py-2 text-right text-xs sm:pr-5">{{ $rate($team->converted_count, $team->assigned_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
@endsection
