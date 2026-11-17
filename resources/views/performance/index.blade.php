@extends('layouts.app', ['title' => 'Performance'])

@section('page-title', $viewer->isSalesPerson() ? 'My performance' : 'Performance')
@section('page-description', 'KPIs for '.$period['label'].', measured against the targets an administrator sets in Settings.')

@section('content')
    @php
        $money = fn (float $amount): string => '৳ '.number_format($amount);
        $pct = fn ($value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
        $minutes = fn ($value): string => $value === null ? '—' : ($value >= 120 ? round($value / 60, 1).' h' : $value.' min');
        $barOf = fn ($value, $target, $higher = true) => $value === null || $target <= 0 ? null : ($higher ? $value / $target * 100 : ($value <= 0 ? 100 : $target / $value * 100));
        $gradeBadge = ['met' => 'green', 'near' => 'amber', 'below' => 'red'];
    @endphp

    <x-ui.period :range="$period['range']" :start="$period['start']" :end="$period['end']" route="performance.index" />

    @if (isset($mine))
        <section class="flex flex-col gap-3" aria-labelledby="mine-heading">
            <h2 class="text-sm font-bold" id="mine-heading">My KPIs</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.kpi label="Calls per working day" :value="number_format($mine['calls_per_day'], 1)" :target="$targets['calls_per_day']" :grade="$mine['status']['calls_per_day']"
                    :progress="$barOf($mine['calls_per_day'], $targets['calls_per_day'])" :hint="number_format($mine['calls']).' calls in total'" />
                <x-ui.kpi label="First-call time (median)" :value="$minutes($mine['first_call_minutes'])" :target="$targets['first_call_minutes'].' min or less'" :grade="$mine['status']['first_call_minutes']"
                    :progress="$barOf($mine['first_call_minutes'], $targets['first_call_minutes'], false)" :hint="$mine['uncalled'].' assigned leads not yet called'" />
                <x-ui.kpi label="Follow-ups kept on time" :value="$pct($mine['followup_on_time'])" :target="$targets['followup_on_time'].'%'" :grade="$mine['status']['followup_on_time']"
                    :progress="$barOf($mine['followup_on_time'], $targets['followup_on_time'])" :hint="$mine['followups_kept'].' of '.$mine['followups_due'].' due'" />
                <x-ui.kpi label="Conversion rate" :value="$pct($mine['conversion'])" :target="$targets['conversion'].'%'" :grade="$mine['status']['conversion']"
                    :progress="$barOf($mine['conversion'], $targets['conversion'])" :hint="$mine['converted'].' of '.$mine['assigned'].' assigned leads'" />
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-ui.stat label="Leads received" :value="number_format($mine['assigned'])" hint="Assigned in this period" />
                <x-ui.stat label="Revenue collected" :value="$money($mine['revenue'])" hint="Verified payments of your enrollments" />
                <x-ui.stat label="Overdue follow-ups" :value="number_format($mine['overdue_now'])" :alert="$mine['overdue_now'] > 0" :href="route('follow-ups.mine')" hint="Right now" />
                <x-ui.stat label="Converted" :value="number_format($mine['converted'])" hint="Leads that became students" />
            </div>

            <x-ui.card title="Last 6 weeks" flush>
                <x-slot:meta>Calls logged and leads received, by week starting</x-slot:meta>
                @php $peak = max(1, collect($weekly)->max(fn ($week) => max($week['calls'], $week['assigned']))); @endphp
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] border-collapse text-left">
                        <thead>
                            <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Week of</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Calls</th>
                                <th class="px-4 py-2 font-semibold sm:pr-5" scope="col">Leads received</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px]">
                            @foreach ($weekly as $week)
                                <tr class="border-b border-pedago-line last:border-0">
                                    <th class="px-4 py-2 font-medium sm:pl-5" scope="row">{{ $week['label'] }}</th>
                                    <td class="px-2 py-2"><span class="flex items-center gap-2"><span class="block h-2.5 w-32 rounded-sm bg-pedago-canvas" aria-hidden="true"><span class="block h-full rounded-sm bg-pedago-blue" style="width: {{ $week['calls'] > 0 ? max(3, (int) round($week['calls'] / $peak * 100)) : 0 }}%"></span></span><span class="num text-xs font-semibold">{{ $week['calls'] }}</span></span></td>
                                    <td class="px-4 py-2 sm:pr-5"><span class="flex items-center gap-2"><span class="block h-2.5 w-32 rounded-sm bg-pedago-canvas" aria-hidden="true"><span class="block h-full rounded-sm bg-pedago-blue-soft" style="width: {{ $week['assigned'] > 0 ? max(3, (int) round($week['assigned'] / $peak * 100)) : 0 }}%"></span></span><span class="num text-xs font-semibold">{{ $week['assigned'] }}</span></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </section>
    @endif

    @if (isset($rows))
        <section class="flex flex-col gap-3" aria-labelledby="team-heading">
            <h2 class="text-sm font-bold" id="team-heading">{{ $viewer->isAdmin() ? 'All sales people' : ($viewer->role === 'Team Leader' ? 'My team' : 'My teams') }}</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <x-ui.stat label="Sales people" :value="number_format($summary['people'])" hint="Executives and team leaders" />
                <x-ui.stat label="Calls per working day" :value="number_format($summary['calls_per_day'], 1)" :hint="'Average each · target '.$targets['calls_per_day']" />
                <x-ui.stat label="Follow-ups kept on time" :value="$pct($summary['followup_on_time'])" :hint="'Target '.$targets['followup_on_time'].'%'" />
                <x-ui.stat label="Conversion rate" :value="$pct($summary['conversion'])" :hint="'Target '.$targets['conversion'].'%'" />
                <x-ui.stat label="Revenue collected" :value="$money($summary['revenue'])" hint="Verified in this period" />
            </div>

            <x-ui.card title="Performance by person" flush>
                <x-slot:meta>Most conversions first · colours show against target</x-slot:meta>
                @if ($rows === [])
                    <x-ui.empty class="border-t border-pedago-line" title="No sales people to show" icon="users" />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] border-collapse text-left">
                            <thead>
                                <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Person</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Received</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Calls / day</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">First call</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">On-time follow-ups</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Converted</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Rate</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Revenue</th>
                                    <th class="px-4 py-2 text-right font-semibold sm:pr-5" scope="col">Overdue now</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13px]">
                                @foreach ($rows as $id => $row)
                                    @php $person = $people[$id]; @endphp
                                    <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                        <th class="px-4 py-2 font-medium sm:pl-5" scope="row">
                                            <span class="block max-w-48 truncate">{{ $person->name }}</span>
                                            <span class="block text-xs font-normal text-pedago-muted">{{ $person->role === 'Team Leader' ? 'Team leader' : 'Sales executive' }} · {{ $person->effectiveTeam()?->name ?? 'No team' }}</span>
                                        </th>
                                        <td class="num px-2 py-2 text-right text-xs">{{ $row['assigned'] }}</td>
                                        @foreach ([['calls_per_day', number_format($row['calls_per_day'], 1)], ['first_call_minutes', $minutes($row['first_call_minutes'])], ['followup_on_time', $pct($row['followup_on_time'])]] as [$key, $shown])
                                            <td class="px-2 py-2 text-right"><span @class(['num inline-block rounded px-1.5 py-0.5 text-xs font-semibold', 'bg-pedago-green-tint text-pedago-green' => $row['status'][$key] === 'met', 'bg-pedago-amber-tint text-pedago-amber' => $row['status'][$key] === 'near', 'bg-pedago-red-tint text-pedago-red' => $row['status'][$key] === 'below'])>{{ $shown }}</span></td>
                                        @endforeach
                                        <td class="num px-2 py-2 text-right text-xs font-semibold">{{ $row['converted'] }}</td>
                                        <td class="px-2 py-2 text-right"><span @class(['num inline-block rounded px-1.5 py-0.5 text-xs font-semibold', 'bg-pedago-green-tint text-pedago-green' => $row['status']['conversion'] === 'met', 'bg-pedago-amber-tint text-pedago-amber' => $row['status']['conversion'] === 'near', 'bg-pedago-red-tint text-pedago-red' => $row['status']['conversion'] === 'below'])>{{ $pct($row['conversion']) }}</span></td>
                                        <td class="num px-2 py-2 text-right text-xs">{{ $money($row['revenue']) }}</td>
                                        <td @class(['num px-4 py-2 text-right text-xs sm:pr-5', 'font-semibold text-pedago-red' => $row['overdue_now'] > 0])>{{ $row['overdue_now'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </section>
    @endif

    @if (isset($marketing))
        <section class="flex flex-col gap-3" aria-labelledby="marketing-heading">
            <h2 class="text-sm font-bold" id="marketing-heading">Marketing</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <x-ui.stat label="Leads received" :value="number_format($marketing['received'])" hint="Created in this period" />
                <x-ui.stat label="Converted" :value="number_format($marketing['converted'])" :hint="$pct($marketing['received'] > 0 ? $marketing['converted'] / $marketing['received'] * 100 : null).' of leads received'" />
                <x-ui.stat label="Duplicate share" :value="$pct($marketing['duplicate_share'])" :hint="$marketing['duplicates'].' sent to duplicate review'" />
                <x-ui.stat label="Waiting for an owner" :value="number_format($marketing['waiting'])" hint="New leads not yet assigned" />
                <x-ui.stat label="Assigned, never called" :value="number_format($marketing['uncalled'])" :alert="$marketing['uncalled'] > 0" hint="Leads that are cooling" />
            </div>
            <x-ui.card title="Campaign performance" flush>
                @if ($marketing['campaigns']->isEmpty())
                    <x-ui.empty class="border-t border-pedago-line" title="No campaign brought leads in this period" icon="flag" />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] border-collapse text-left">
                            <thead>
                                <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Campaign</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Leads</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Waiting</th>
                                    <th class="px-2 py-2 text-right font-semibold" scope="col">Converted</th>
                                    <th class="px-4 py-2 text-right font-semibold sm:pr-5" scope="col">Rate</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13px]">
                                @foreach ($marketing['campaigns'] as $campaign)
                                    <tr class="border-b border-pedago-line last:border-0 hover:bg-pedago-row">
                                        <th class="px-4 py-2 font-medium sm:pl-5" scope="row"><span class="block max-w-56 truncate">{{ $campaign->campaign_name ?: 'Unnamed campaign' }}</span><span class="block text-xs font-normal text-pedago-muted">{{ \App\Models\LeadSource::CHANNELS[$campaign->channel] ?? $campaign->channel }}</span></th>
                                        <td class="num px-2 py-2 text-right text-xs font-semibold">{{ $campaign->leads_count }}</td>
                                        <td class="num px-2 py-2 text-right text-xs">{{ $campaign->waiting_count }}</td>
                                        <td class="num px-2 py-2 text-right text-xs">{{ $campaign->converted_count }}</td>
                                        <td class="num px-4 py-2 text-right text-xs font-semibold sm:pr-5">{{ $pct($campaign->leads_count > 0 ? $campaign->converted_count / $campaign->leads_count * 100 : null) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </section>
    @endif

    @if (isset($accounts))
        <section class="flex flex-col gap-3" aria-labelledby="accounts-heading">
            <h2 class="text-sm font-bold" id="accounts-heading">Accounts</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <x-ui.stat label="Payments verified" :value="number_format($accounts['verified_count'])" :hint="$money($accounts['verified_amount']).' confirmed'" />
                <x-ui.stat label="Median decision time" :value="$accounts['median_hours'] === null ? '—' : $accounts['median_hours'].' h'" hint="From submission to verify or reject" />
                <x-ui.stat label="Rejection rate" :value="$pct($accounts['rejection_rate'])" :hint="$accounts['rejected_count'].' rejected'" />
                <x-ui.stat label="Waiting for review" :value="number_format($accounts['pending'])" :href="route('payments.index')" :alert="$accounts['pending'] > 0" :hint="$accounts['oldest_pending'] ? 'Oldest '.\Illuminate\Support\Carbon::parse($accounts['oldest_pending'])->diffForHumans(null, true) : 'Nothing waiting'" />
            </div>
        </section>
    @endif

    <p class="text-xs leading-5 text-pedago-muted">Calls per working day counts every call logged divided by the days in the period up to today, leaving out Fridays. First-call time is the median wait from assignment to the first logged call. A follow-up is kept on time when it is done within {{ \App\Actions\PerformanceMetrics::FOLLOWUP_GRACE_MINUTES }} minutes of the promised time. Conversion counts leads assigned in the period that have since converted.</p>
@endsection
