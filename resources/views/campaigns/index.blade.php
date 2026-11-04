@extends('layouts.app', ['title' => 'Campaigns'])

@section('page-title', 'Campaigns')
@section('page-description', 'Manage campaigns and track leads by source.')
@section('page-actions')
    <span class="hidden rounded-full border border-pedago-line bg-white px-3 py-1.5 text-xs font-semibold text-pedago-muted sm:inline-flex">{{ number_format($campaigns->total()) }} campaigns</span>
    <a class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" href="#campaign-create">Create campaign</a>
@endsection

@section('content')
    <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6" id="campaign-create" aria-labelledby="create-campaign-heading">
        <div class="mb-5 flex flex-col gap-1">
            <h2 class="text-sm font-bold" id="create-campaign-heading">Create campaign</h2>
            <p class="text-xs text-pedago-muted">Set up a campaign and choose how its leads should be distributed.</p>
        </div>

        <form class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" method="POST" action="{{ route('campaigns.store') }}">
            @csrf
            <label class="flex flex-col gap-2 text-sm font-semibold" for="campaign_name">
                Campaign name <span class="text-pedago-red">*</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="campaign_name" maxlength="150" name="campaign_name" type="text" value="{{ old('campaign_name') }}" required>
            </label>

            <label class="flex flex-col gap-2 text-sm font-semibold" for="channel">
                Source channel <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="channel" name="channel" required>
                    <option value="">Select a source</option>
                    @foreach ($channels as $value => $label)
                        <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-2 text-sm font-semibold" for="distribution_mode">
                Distribution mode <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="distribution_mode" name="distribution_mode" required>
                    @foreach ($distributionModes as $mode)
                        <option value="{{ $mode }}" @selected(old('distribution_mode', 'Auto') === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end">
                <button class="inline-flex w-full items-center justify-center rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue sm:w-fit" type="submit">
                    Create campaign
                </button>
            </div>
        </form>
    </section>

    <section class="flex flex-col gap-4 rounded-xl border border-pedago-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="campaign-filter-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-bold" id="campaign-filter-heading">Find campaigns</h2>
            <a class="text-sm font-semibold text-pedago-blue-dark underline underline-offset-2" href="{{ route('campaigns.index') }}">Clear filters</a>
        </div>
        <form class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,2fr)_minmax(10rem,1fr)_minmax(12rem,1fr)_auto]" method="GET" action="{{ route('campaigns.index') }}">
            <label class="flex flex-col gap-2 text-sm font-semibold" for="search">
                Campaign name
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search campaigns">
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="filter-channel">
                Channel
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="filter-channel" name="channel">
                    <option value="">All channels</option>
                    @foreach ($channels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['channel'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="sort">
                Sort by lead count
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="sort" name="sort">
                    <option value="leads_desc" @selected($filters['sort'] === 'leads_desc')>Most leads first</option>
                    <option value="leads_asc" @selected($filters['sort'] === 'leads_asc')>Fewest leads first</option>
                </select>
            </label>
            <div class="flex items-end">
                <button class="inline-flex w-full items-center justify-center rounded-md border border-pedago-line px-5 py-2.5 text-sm font-semibold text-pedago-ink transition hover:border-pedago-blue hover:bg-pedago-canvas sm:w-fit" type="submit">
                    Apply filters
                </button>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-pedago-line bg-white shadow-sm" aria-labelledby="campaign-list-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-pedago-line px-5 py-4">
            <h2 class="text-sm font-bold" id="campaign-list-heading">All campaigns</h2>
            <span class="text-xs text-pedago-muted">{{ number_format($campaigns->total()) }} campaigns</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] border-collapse text-left">
                <thead class="bg-pedago-canvas text-[11px] uppercase tracking-wide text-pedago-muted">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Campaign</th>
                        <th class="px-5 py-3 font-semibold">Source</th>
                        <th class="px-5 py-3 font-semibold">Distribution</th>
                        <th class="px-5 py-3 font-semibold">Leads</th>
                        <th class="px-5 py-3 font-semibold">Created date</th>
                        <th class="px-5 py-3 font-semibold">Created by</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pedago-line text-sm">
                    @forelse ($campaigns as $campaign)
                        <tr class="transition-colors hover:bg-pedago-canvas/70">
                            <td class="px-5 py-3 font-semibold">{{ $campaign->campaign_name }}</td>
                            <td class="px-5 py-3 text-pedago-muted">{{ $channels[$campaign->channel] ?? $campaign->channel }}</td>
                            <td class="px-5 py-3">{{ $campaign->distribution_mode }}</td>
                            <td class="px-5 py-3 font-mono">{{ number_format($campaign->leads_count) }}</td>
                            <td class="px-5 py-3">{{ $campaign->created_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-5 py-3">{{ $campaign->createdBy?->name ?? 'Unknown' }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-pedago-green-tint text-pedago-green' => $campaign->is_active,
                                    'bg-pedago-canvas text-pedago-muted' => ! $campaign->is_active,
                                ])>
                                    {{ $campaign->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <a class="rounded-md border border-pedago-line px-3 py-1.5 text-xs font-semibold text-pedago-blue-dark hover:bg-pedago-tint" href="{{ route('campaigns.edit', $campaign) }}">Edit</a>
                                    <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" onsubmit="return confirm('Delete this campaign?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($campaign->leads_count > 0) title="{{ $campaign->leads_count > 0 ? 'Campaigns with leads cannot be deleted' : 'Delete campaign' }}">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                                @if ($campaign->leads_count > 0)
                                    <p class="mt-1 text-xs text-pedago-muted">Cannot delete while leads are attached.</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-10 text-center text-sm text-pedago-muted" colspan="8">
                                No campaigns match your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($campaigns->hasPages())
            <div class="border-t border-pedago-line px-5 py-4">
                {{ $campaigns->links() }}
            </div>
        @endif
    </section>
@endsection
