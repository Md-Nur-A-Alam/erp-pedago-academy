@php
    $rate = fn (int $part, int $whole): string => $whole > 0 ? number_format($part / $whole * 100, 1).'%' : '—';
    $maxChannel = max(1, (int) $channels->max());
    $maxCampaign = max(1, (int) $campaigns->max('leads_count'));
@endphp

<form class="flex flex-wrap items-center gap-1.5" method="GET" action="{{ route('dashboard') }}" aria-label="Dashboard date range">
    @foreach (['today' => 'Today', 'this_month' => 'This month', 'three_months' => '3 months', 'this_year' => 'This year'] as $preset => $label)
        <button @class([
            'min-h-9 rounded-md border px-3 text-sm font-semibold transition-colors duration-150',
            'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' => $range === $preset,
            'border-pedago-line bg-white text-pedago-ink hover:bg-pedago-canvas' => $range !== $preset,
        ]) type="submit" name="range" value="{{ $preset }}" aria-pressed="{{ $range === $preset ? 'true' : 'false' }}">{{ $label }}</button>
    @endforeach
    <span class="ml-1 text-xs text-pedago-muted">{{ $rangeLabel }}</span>
</form>

<section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" aria-label="Marketing summary">
    <x-ui.stat label="Leads received" :value="number_format($metrics['received'])" :href="route('leads.index')" hint="In this period" />
    <x-ui.stat label="Converted" :value="number_format($metrics['converted'])" :hint="$rate($metrics['converted'], $metrics['received']).' of leads received'" />
    <x-ui.stat label="Conversion rate" :value="$metrics['received'] > 0 ? number_format($metrics['conversion_rate'], 1).'%' : '—'" hint="Converted ÷ received" />
    <x-ui.stat label="Waiting for an owner" :value="number_format($metrics['waiting'])" hint="New leads not yet assigned" />
    <x-ui.stat label="In duplicate review" :value="number_format($metrics['duplicates'])" hint="Held until an admin decides" />
    <x-ui.stat label="Active campaigns" :value="number_format($metrics['active_campaigns'])" :href="route('campaigns.index')" hint="Open for new leads" />
</section>

<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
    <x-ui.card title="Leads by channel">
        <x-slot:meta>{{ $rangeLabel }}</x-slot:meta>
        @if ($channels->isEmpty())
            <x-ui.empty title="No leads in this period" icon="inbox">Upload leads or choose a wider period.</x-ui.empty>
        @else
            <ul class="flex flex-col gap-1.5">
                @foreach ($channels as $channel => $count)
                    <li class="grid grid-cols-[7rem_minmax(0,1fr)_3rem] items-center gap-2.5 text-[13px]">
                        <span class="truncate">{{ \App\Models\LeadSource::CHANNELS[$channel] ?? $channel }}</span>
                        <span class="block h-3.5 rounded-sm bg-pedago-canvas"><span class="block h-full rounded-sm bg-pedago-blue" style="width: {{ max(2, (int) round($count / $maxChannel * 100)) }}%"></span></span>
                        <span class="num text-right text-xs font-semibold">{{ number_format($count) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <x-ui.button :href="route('leads.upload')" icon="upload">Upload leads</x-ui.button>
            <x-ui.button :href="route('campaigns.index')" variant="secondary" icon="flag">Campaigns</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card title="Campaign performance" flush>
        <x-slot:meta>Top {{ $campaigns->count() }} by leads received</x-slot:meta>
        @if ($campaigns->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No campaigns yet" icon="flag">Create a campaign before uploading leads.</x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] border-collapse text-left">
                    <thead>
                        <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Campaign</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Leads</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Waiting</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Converted</th>
                            <th class="px-4 py-2 text-right font-semibold sm:pr-5" scope="col">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($campaigns as $campaign)
                            <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                <th class="px-4 py-2 font-medium sm:pl-5" scope="row">
                                    <span class="block max-w-56 truncate">{{ $campaign->campaign_name ?: 'Unnamed campaign' }}</span>
                                    <span class="block text-xs font-normal text-pedago-muted">{{ \App\Models\LeadSource::CHANNELS[$campaign->channel] ?? $campaign->channel }}{{ $campaign->is_active ? '' : ' · inactive' }}</span>
                                </th>
                                <td class="px-2 py-2">
                                    <span class="flex items-center gap-2">
                                        <span class="block h-2.5 w-20 rounded-sm bg-pedago-canvas" aria-hidden="true"><span class="block h-full rounded-sm bg-pedago-blue" style="width: {{ $campaign->leads_count > 0 ? max(3, (int) round($campaign->leads_count / $maxCampaign * 100)) : 0 }}%"></span></span>
                                        <span class="num text-xs font-semibold">{{ number_format($campaign->leads_count) }}</span>
                                    </span>
                                </td>
                                <td class="num px-2 py-2 text-right text-xs">{{ number_format($campaign->waiting_count) }}</td>
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
