@extends('layouts.app', ['title' => 'Edit campaign'])

@section('page-title', 'Edit campaign')
@section('page-description', 'Existing leads stay linked to this campaign.')
@section('page-actions')
    <a class="rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('campaigns.index') }}">Campaigns</a>
@endsection

@section('content')
    <section class="mx-auto w-full max-w-3xl rounded-lg border border-pedago-line bg-white p-5 sm:p-8">
        <form class="grid gap-5 sm:grid-cols-2" method="POST" action="{{ route('campaigns.update', $campaign) }}">
            @csrf
            @method('PUT')
            <label class="flex flex-col gap-2 text-sm font-semibold sm:col-span-2" for="campaign_name">
                Campaign name <span class="text-pedago-red">*</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="campaign_name" maxlength="150" name="campaign_name" type="text" value="{{ old('campaign_name', $campaign->campaign_name) }}" required>
            </label>

            <label class="flex flex-col gap-2 text-sm font-semibold" for="channel">
                Source channel <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="channel" name="channel" required>
                    @foreach ($channels as $value => $label)
                        <option value="{{ $value }}" @selected(old('channel', $campaign->channel) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-2 text-sm font-semibold" for="distribution_mode">
                Distribution mode <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="distribution_mode" name="distribution_mode" required>
                    @foreach ($distributionModes as $mode)
                        <option value="{{ $mode }}" @selected(old('distribution_mode', $campaign->distribution_mode) === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex flex-wrap gap-3 sm:col-span-2">
                <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">
                    Save changes
                </button>
                <a class="inline-flex items-center justify-center rounded-md border border-pedago-line px-5 py-2.5 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" href="{{ route('campaigns.index') }}">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
