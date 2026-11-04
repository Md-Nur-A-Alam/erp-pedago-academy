@extends('layouts.app', ['title' => 'Upload leads'])

@section('page-title', 'Upload leads')
@section('page-description', 'Add leads by CSV import or enter them individually.')
@section('page-actions')
    <div class="inline-flex rounded-md border border-pedago-line bg-pedago-canvas p-1" role="group" aria-label="Lead entry method">
        <a @class([
            'rounded px-3 py-1.5 text-xs font-semibold transition-colors sm:px-4 sm:text-sm',
            'bg-pedago-blue text-white shadow-sm' => $mode === 'bulk',
            'text-pedago-muted hover:bg-white hover:text-pedago-ink' => $mode !== 'bulk',
        ]) href="{{ route('leads.upload', ['mode' => 'bulk']) }}" @if ($mode === 'bulk') aria-current="page" @endif>
            Bulk import
        </a>
        <a @class([
            'rounded px-3 py-1.5 text-xs font-semibold transition-colors sm:px-4 sm:text-sm',
            'bg-pedago-blue text-white shadow-sm' => $mode === 'single',
            'text-pedago-muted hover:bg-white hover:text-pedago-ink' => $mode !== 'single',
        ]) href="{{ route('leads.upload', ['mode' => 'single']) }}" @if ($mode === 'single') aria-current="page" @endif>
            Single lead
        </a>
    </div>
    @if ($mode === 'bulk')
        <button class="inline-flex items-center justify-center gap-2 rounded-md border border-pedago-blue/20 bg-pedago-tint px-3 py-2 text-xs font-semibold text-pedago-blue-dark transition hover:border-pedago-blue/40 hover:bg-pedago-blue/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue sm:px-4 sm:text-sm" form="lead-template-form" type="submit">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75v10.5m0 0 3.75-3.75M12 14.25l-3.75-3.75M4.5 16.5v2.25A1.5 1.5 0 0 0 6 20.25h12a1.5 1.5 0 0 0 1.5-1.5V16.5" />
            </svg>
            Download CSV template
        </button>
    @endif
@endsection

@section('content')
    @if (session('import_result'))
        @php($result = session('import_result'))
        <section class="rounded-xl border border-pedago-green/20 bg-pedago-green-tint p-5 shadow-sm" role="status" aria-label="Import result">
            <h2 class="font-semibold text-pedago-green">Import complete</h2>
            <p class="mt-1 text-sm text-pedago-green">
                {{ number_format($result['imported']) }} leads imported; {{ number_format($result['duplicates']) }} possible duplicates queued for review, from {{ number_format($result['total']) }} rows.
            </p>
        </section>
    @endif

    @if (session('single_result'))
        <div class="rounded-lg border border-pedago-green/20 bg-pedago-green-tint px-4 py-3 text-sm text-pedago-green shadow-sm" role="status">
            {{ session('single_result') }}
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,1fr)]">
        @if ($mode === 'bulk')
            <div class="flex flex-col gap-4">
                <form id="lead-import-form" method="POST" action="{{ route('leads.upload.store') }}" enctype="multipart/form-data">
                    @csrf
                </form>

                <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6" aria-labelledby="campaign-heading">
                    <div class="mb-5 flex items-start gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-pedago-blue text-xs font-bold text-white" aria-hidden="true">1</span>
                        <div>
                            <h2 class="text-sm font-bold" id="campaign-heading">Select campaign</h2>
                            <p class="mt-1 text-xs text-pedago-muted">Choose the source details that will be attached to every imported lead.</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="channel">1a · Channel <span class="text-pedago-red">*</span></label>
                            <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="channel" data-channel-select form="lead-import-form" name="channel" required>
                                <option value="">Select a channel</option>
                                @foreach ($channels as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedChannel === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('channel')
                                <p class="text-xs text-pedago-red">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="campaign_name">1b · Campaign <span class="text-xs font-normal text-pedago-muted">(optional)</span></label>
                            <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20 disabled:cursor-not-allowed disabled:bg-pedago-canvas" id="campaign_name" data-campaign-select data-channel-select-id="channel" form="lead-import-form" name="campaign_name" @disabled(! $selectedChannel)>
                                <option value="">No campaign</option>
                                @foreach ($campaignsByChannel[$selectedChannel] ?? [] as $campaignName)
                                    <option value="{{ $campaignName }}" @selected(old('campaign_name') === $campaignName)>{{ $campaignName }}</option>
                                @endforeach
                            </select>
                            @error('campaign_name')
                                <p class="text-xs text-pedago-red">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <p class="mt-4 rounded-md bg-pedago-tint/60 px-3 py-2 text-xs leading-5 text-pedago-blue-dark">Channel and campaign details are tagged on every imported row. They do not need to be columns in your file.</p>
                </section>

                <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6" aria-labelledby="columns-heading">
                    <form id="lead-template-form" method="GET" action="{{ route('leads.template') }}"></form>
                    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-pedago-blue text-xs font-bold text-white" aria-hidden="true">2</span>
                            <div>
                                <h2 class="text-sm font-bold" id="columns-heading">Choose columns for your sheet</h2>
                                <p class="mt-1 text-xs text-pedago-muted">Select the fields you want in your CSV template. All are selected by default.</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-pedago-canvas px-3 py-1 text-xs font-semibold text-pedago-muted">{{ count($columns) }} columns available</span>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($columns as $column)
                            <div>
                                <label @class([
                                    'flex min-h-16 items-start gap-3 rounded-lg border px-3 py-3 transition-colors',
                                    'cursor-not-allowed border-pedago-blue/20 bg-pedago-tint/40' => $column['required'],
                                    'cursor-pointer border-pedago-line hover:border-pedago-blue/40 hover:bg-pedago-canvas/70' => ! $column['required'],
                                ])>
                                    <input class="mt-0.5 size-4 rounded border-pedago-line text-pedago-blue focus:ring-pedago-blue/30" form="lead-template-form" name="columns[]" type="checkbox" value="{{ $column['name'] }}" @checked(true) @disabled($column['required'])>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-pedago-ink">{{ $column['label'] }}</span>
                                        <span class="mt-0.5 block text-xs text-pedago-muted">{{ $column['description'] }} · <code class="text-[11px]">{{ $column['name'] }}</code></span>
                                    </span>
                                </label>
                                @if ($column['required'])
                                    <input form="lead-template-form" name="columns[]" type="hidden" value="{{ $column['name'] }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-4 text-xs leading-5 text-pedago-muted">Student name and primary phone are required and will always be included in the downloaded template.</p>
                </section>
            </div>

            <aside class="flex flex-col gap-4">
                <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6" aria-labelledby="upload-heading">
                    <div class="mb-5 flex items-start gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-pedago-blue text-xs font-bold text-white" aria-hidden="true">3</span>
                        <div>
                            <h2 class="text-sm font-bold" id="upload-heading">Upload the filled file</h2>
                            <p class="mt-1 text-xs text-pedago-muted">Use the CSV template so the column headings match.</p>
                        </div>
                    </div>

                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-pedago-blue/40 bg-pedago-canvas/60 px-4 py-8 text-center transition hover:border-pedago-blue hover:bg-pedago-tint/60" for="file">
                        <svg class="size-8 text-pedago-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3.75m0 0L7.5 8.25M12 3.75l4.5 4.5M4.5 15v3.75A1.5 1.5 0 0 0 6 20.25h12a1.5 1.5 0 0 0 1.5-1.5V15" />
                        </svg>
                        <span class="text-sm font-semibold text-pedago-blue-dark">Choose a completed CSV file</span>
                        <span class="text-xs text-pedago-muted">Maximum 500 rows · 10 MB per file</span>
                        <input class="mt-2 block w-full max-w-sm text-sm text-pedago-muted file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-pedago-blue-dark hover:file:bg-pedago-tint" id="file" form="lead-import-form" name="file" type="file" accept=".csv,text/csv" required>
                    </label>
                    @error('file')
                        <p class="mt-2 text-xs text-pedago-red">{{ $message }}</p>
                    @enderror
                    <button class="mt-4 inline-flex w-full items-center justify-center rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" form="lead-import-form" type="submit">
                        Validate and import leads
                    </button>
                </section>

                <section class="rounded-lg border border-pedago-line bg-white p-5" aria-labelledby="checks-heading">
                    <h2 class="text-sm font-bold" id="checks-heading">Before importing</h2>
                    <ul class="mt-3 flex flex-col divide-y divide-pedago-line">
                        <li class="py-3">
                            <p class="text-sm font-semibold">Phone numbers</p>
                            <p class="mt-1 text-xs leading-5 text-pedago-muted">Use 7–24 digits, optionally beginning with +. Spaces and common separators are removed.</p>
                        </li>
                        <li class="py-3">
                            <p class="text-sm font-semibold">Possible duplicates</p>
                            <p class="mt-1 text-xs leading-5 text-pedago-muted">A matching contact number is queued for duplicate review instead of being imported again.</p>
                        </li>
                        <li class="py-3">
                            <p class="text-sm font-semibold">All-or-nothing validation</p>
                            <p class="mt-1 text-xs leading-5 text-pedago-muted">If any row is invalid, no leads are imported. Correct the reported row and upload again.</p>
                        </li>
                    </ul>
                </section>
            </aside>
        @else
            <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6" aria-labelledby="single-heading">
                <h2 class="text-sm font-bold" id="single-heading">Add a single lead</h2>
                <p class="mt-1 text-xs text-pedago-muted">Choose the source, then enter the lead details. Possible phone matches go to duplicate review.</p>
                <form class="mt-5 flex flex-col gap-5" method="POST" action="{{ route('leads.store') }}">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="single-channel">Channel <span class="text-pedago-red">*</span></label>
                            <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="single-channel" data-channel-select name="channel" required>
                                <option value="">Select a channel</option>
                                @foreach ($channels as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedChannel === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="single-campaign">Campaign name</label>
                            <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20 disabled:cursor-not-allowed disabled:bg-pedago-canvas" id="single-campaign" data-campaign-select data-channel-select-id="single-channel" name="campaign_name" @disabled(! $selectedChannel)>
                                <option value="">No campaign</option>
                                @foreach ($campaignsByChannel[$selectedChannel] ?? [] as $campaignName)
                                    <option value="{{ $campaignName }}" @selected(old('campaign_name') === $campaignName)>{{ $campaignName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="student-name">Student name <span class="text-pedago-red">*</span></label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="student-name" maxlength="150" name="student_name" type="text" value="{{ old('student_name') }}" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="contact-number">Primary phone <span class="text-pedago-red">*</span></label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="contact-number" maxlength="25" name="contact_number" type="tel" value="{{ old('contact_number') }}" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="guardian-name">Guardian name</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="guardian-name" maxlength="150" name="guardian_name" type="text" value="{{ old('guardian_name') }}">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="whatsapp-number">WhatsApp number</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="whatsapp-number" maxlength="25" name="whatsapp_number" type="tel" value="{{ old('whatsapp_number') }}">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="alternative-number">Alternative number</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="alternative-number" maxlength="25" name="alternative_contact_number" type="tel" value="{{ old('alternative_contact_number') }}">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="lead-email">Email</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="lead-email" maxlength="120" name="email" type="email" value="{{ old('email') }}">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="lead-location">Location</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="lead-location" maxlength="255" name="location" type="text" value="{{ old('location') }}">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="lead-age">Age</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="lead-age" min="0" max="120" name="age" type="number" value="{{ old('age') }}">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="lead-class">Class</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="lead-class" maxlength="50" name="class" type="text" value="{{ old('class') }}">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold" for="parent-type">Student / parent</label>
                            <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="parent-type" maxlength="30" name="student_parent_type" type="text" value="{{ old('student_parent_type') }}">
                        </div>
                        <div class="flex flex-col gap-2 sm:col-span-2">
                            <label class="text-sm font-semibold" for="student-background">Background or notes</label>
                            <textarea class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="student-background" name="student_background" rows="3">{{ old('student_background') }}</textarea>
                        </div>
                    </div>
                    <button class="inline-flex w-fit items-center justify-center rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">
                        Add lead
                    </button>
                </form>
            </section>
        @endif

        @if ($mode === 'single')
            <aside class="flex flex-col gap-4">
                <section class="rounded-lg border border-pedago-line bg-white p-5" aria-labelledby="columns-heading">
                    <h2 class="text-sm font-bold" id="columns-heading">Template columns</h2>
                    <p class="mt-2 text-xs leading-5 text-pedago-muted">Only student name and contact number are required. Leave optional cells blank when you do not have that information.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($columns as $column)
                            <code class="rounded bg-pedago-canvas px-2 py-1 text-[11px] text-pedago-blue-dark">{{ $column['name'] }}</code>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-lg border border-pedago-line bg-white p-5" aria-labelledby="checks-heading">
                    <h2 class="text-sm font-bold" id="checks-heading">Before adding a lead</h2>
                    <ul class="mt-3 flex flex-col divide-y divide-pedago-line">
                        <li class="py-3">
                            <p class="text-sm font-semibold">Phone numbers</p>
                            <p class="mt-1 text-xs leading-5 text-pedago-muted">Use 7–24 digits, optionally beginning with +. Spaces and common separators are removed.</p>
                        </li>
                        <li class="py-3">
                            <p class="text-sm font-semibold">Possible duplicates</p>
                            <p class="mt-1 text-xs leading-5 text-pedago-muted">A matching contact number is not imported as a new lead; it is queued for duplicate review.</p>
                        </li>
                    </ul>
                </section>
            </aside>
        @endif
    </div>

    <script>
        const campaignsByChannel = @js($campaignsByChannel);

        document.querySelectorAll('[data-campaign-select]').forEach((campaignSelect) => {
            const channelSelect = document.getElementById(campaignSelect.dataset.channelSelectId);

            const updateCampaigns = (preferredCampaign = '') => {
                const campaignNames = campaignsByChannel[channelSelect.value] || [];
                campaignSelect.replaceChildren(new Option('No campaign', ''));

                campaignNames.forEach((campaignName) => {
                    campaignSelect.add(new Option(campaignName, campaignName));
                });

                campaignSelect.disabled = channelSelect.value === '';
                campaignSelect.value = campaignNames.includes(preferredCampaign) ? preferredCampaign : '';
            };

            updateCampaigns(campaignSelect.value);
            channelSelect.addEventListener('change', () => updateCampaigns());
        });
    </script>
@endsection
