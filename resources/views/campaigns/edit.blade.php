@extends('layouts.app', ['title' => 'Edit campaign'])

@section('page-title', 'Edit campaign')
@section('page-description', 'Existing leads stay linked to the selected course.')
@section('page-actions')
    <a class="rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('campaigns.index') }}">Campaigns</a>
@endsection

@section('content')
    <section class="mx-auto w-full max-w-3xl rounded-lg border border-pedago-line bg-white p-5 sm:p-8">
        <form class="grid gap-5 sm:grid-cols-2" method="POST" action="{{ route('campaigns.update', $campaign) }}">
            @csrf
            @method('PUT')
            <label class="flex flex-col gap-2 text-sm font-semibold" for="campaign_name">
                Campaign name <span class="text-pedago-red">*</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="campaign_name" maxlength="150" name="campaign_name" type="text" value="{{ old('campaign_name', $campaign->campaign_name) }}" required>
            </label>

            <label class="flex flex-col gap-2 text-sm font-semibold" for="course_id">
                Course <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="course_id" name="course_id" required data-course-select>
                    <option value="">Select a course</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" data-subject="{{ $course->subject?->name }}" data-topic="{{ $course->topic?->name }}" @selected((int) old('course_id', $campaign->course_id) === $course->id)>{{ $course->title }} ({{ $course->code }})</option>
                    @endforeach
                </select>
                @error('course_id')
                    <span class="text-xs font-normal text-pedago-red">{{ $message }}</span>
                @enderror
            </label>

            <div class="flex min-h-11 flex-wrap items-center gap-2 rounded-md bg-pedago-canvas px-3 py-2 text-xs sm:col-span-2" data-taxonomy-preview>
                <span class="text-pedago-muted">Leads will show</span>
                <span class="text-pedago-muted" data-preview-empty>Select a course to view subject and topic.</span>
                <span class="hidden rounded-full bg-pedago-tint px-2 py-1 font-semibold text-pedago-blue-dark" data-preview-subject></span>
                <span class="hidden rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-800" data-preview-topic></span>
                @if (auth()->user()->employee?->isAdmin())
                    <a class="ml-auto text-[11px] font-semibold text-pedago-blue-dark underline" href="{{ route('courses.index') }}">Manage courses</a>
                @endif
            </div>

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

            <div class="flex items-center justify-between gap-3 sm:col-span-2">
                <span class="text-sm font-semibold">Campaign is active</span>
                <div>
                    <input name="is_active" type="hidden" value="0">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-green">
                        <input class="size-4 accent-pedago-blue" name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $campaign->is_active))>
                        {{ old('is_active', $campaign->is_active) ? 'Active' : 'Inactive' }}
                    </label>
                    @error('is_active')
                        <span class="block text-xs font-normal text-pedago-red">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-3 sm:col-span-2">
                <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">
                    Save changes
                </button>
                <a class="inline-flex items-center justify-center rounded-md border border-pedago-line px-5 py-2.5 text-sm font-semibold text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" href="{{ route('campaigns.index') }}">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        const select = document.querySelector('[data-course-select]');
        const preview = document.querySelector('[data-taxonomy-preview]');

        if (select && preview) {
            const subject = preview.querySelector('[data-preview-subject]');
            const topic = preview.querySelector('[data-preview-topic]');
            const empty = preview.querySelector('[data-preview-empty]');

            const updatePreview = () => {
                const option = select.selectedOptions[0];
                const subjectName = option?.dataset.subject || '';
                const topicName = option?.dataset.topic || '';

                subject.textContent = subjectName ? `● ${subjectName}` : '';
                subject.classList.toggle('hidden', !subjectName);
                topic.textContent = topicName ? `● ${topicName}` : '';
                topic.classList.toggle('hidden', !topicName);
                empty.classList.toggle('hidden', Boolean(subjectName || topicName));
            };

            select.addEventListener('change', updatePreview);
            updatePreview();
        }
    </script>
@endpush
