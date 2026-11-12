@extends('layouts.app', ['title' => 'Subjects and topics'])

@section('page-title', 'Subjects and topics')
@section('page-description', 'Manage subjects, topics, and how campaigns present course interests to leads.')
@section('content')
    <div class="grid gap-4 xl:grid-cols-[minmax(19rem,0.82fr)_minmax(34rem,1.8fr)]">
        <div class="flex flex-col gap-4">
            <section class="rounded-lg border border-pedago-line bg-white p-4 shadow-sm" aria-labelledby="create-subject-heading">
                <h2 class="mb-3 text-sm font-bold" id="create-subject-heading">Add a subject</h2>
                <form class="flex flex-col gap-3 sm:flex-row sm:items-end" method="POST" action="{{ route('subjects.store') }}">
                    @csrf
                    <label class="flex min-w-0 flex-1 flex-col gap-2 text-xs font-semibold" for="new-subject-name">
                        Subject name
                        <input class="rounded-md border border-pedago-line px-3 py-2.5 text-sm font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="new-subject-name" name="name" value="{{ old('name') }}" maxlength="100" placeholder="e.g. Science" required>
                    </label>
                    <button class="rounded-md bg-pedago-blue px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark" type="submit">Add subject</button>
                </form>
                @error('name')
                    <p class="mt-2 text-xs text-pedago-red">{{ $message }}</p>
                @enderror
            </section>

            <section class="rounded-lg border border-pedago-line bg-white p-4 shadow-sm" aria-labelledby="subject-list-heading">
                <div class="mb-3 flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-bold" id="subject-list-heading">Subject catalogue</h2>
                    <span class="text-xs text-pedago-muted">{{ $subjects->count() }} subjects</span>
                </div>

                <div class="flex flex-col gap-2">
                    @forelse ($subjects as $subject)
                        <a @class([
                            'rounded-md border p-3 transition-colors',
                            'border-pedago-blue bg-pedago-tint' => $selectedSubject?->is($subject),
                            'border-pedago-line hover:border-pedago-blue/50 hover:bg-pedago-canvas' => ! $selectedSubject?->is($subject),
                        ]) href="{{ route('subjects.index', ['subject' => $subject->id]) }}" @if ($selectedSubject?->is($subject)) aria-current="true" @endif>
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold">{{ $subject->name }}</span>
                                <span class="text-sm text-pedago-muted" aria-hidden="true">›</span>
                            </span>
                            <span class="mt-1 block text-xs text-pedago-muted">
                                {{ $subject->topics_count }} {{ Str::plural('topic', $subject->topics_count) }}
                                · {{ $subject->courses_count }} {{ Str::plural('course', $subject->courses_count) }}
                                · {{ $subject->campaigns_count }} {{ Str::plural('campaign', $subject->campaigns_count) }}
                            </span>
                        </a>
                    @empty
                        <p class="rounded-md border border-pedago-line p-4 text-sm text-pedago-muted">No subjects have been added yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        @if ($selectedSubject)
            <div class="flex flex-col gap-4">
                <section class="rounded-lg border border-pedago-line bg-white p-4 shadow-sm" aria-labelledby="subject-editor-heading">
                    <div class="mb-3 flex items-baseline justify-between gap-2">
                        <h2 class="text-sm font-bold" id="subject-editor-heading">Subject</h2>
                    </div>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start">
                        <form class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center" method="POST" action="{{ route('subjects.update', $selectedSubject) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="selected_subject_id" value="{{ $selectedSubject->id }}">
                            <label class="sr-only" for="subject-{{ $selectedSubject->id }}-name">Subject name</label>
                            <input class="min-w-0 flex-1 rounded-md border border-pedago-line px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="subject-{{ $selectedSubject->id }}-name" name="name" value="{{ $selectedSubject->name }}" maxlength="100" required>
                            <button class="rounded-md bg-pedago-blue px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark" type="submit">Save subject</button>
                        </form>
                        <form method="POST" action="{{ route('subjects.destroy', $selectedSubject) }}" onsubmit="return confirm('Delete this subject and its unused topics? Courses or campaigns using it must be reassigned first.')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="selected_subject_id" value="{{ $selectedSubject->id }}">
                            <button class="rounded-md border border-rose-200 px-3 py-2.5 text-sm font-semibold text-pedago-red transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($selectedSubject->courses_count > 0 || $selectedSubject->campaigns_count > 0) title="{{ $selectedSubject->courses_count > 0 || $selectedSubject->campaigns_count > 0 ? 'A subject used by campaigns or courses cannot be deleted' : 'Delete subject' }}">Delete subject</button>
                        </form>
                    </div>
                    <p class="mt-2 text-xs text-pedago-muted">A subject used by campaigns or courses cannot be deleted.</p>
                    @error('subject')
                        <p class="mt-2 text-xs text-pedago-red">{{ $message }}</p>
                    @enderror
                    @error('name')
                        <p class="mt-2 text-xs text-pedago-red">{{ $message }}</p>
                    @enderror
                </section>

                <section class="rounded-lg border border-pedago-line bg-white p-4 shadow-sm" aria-labelledby="topic-list-heading">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-bold" id="topic-list-heading">Topics</h2>
                        <span class="text-xs text-pedago-muted">{{ $selectedSubject->topics_count }} topics · {{ $selectedSubject->courses_count }} courses</span>
                    </div>

                    @forelse ($selectedSubject->topics as $topic)
                        <div class="grid gap-2 border-b border-pedago-line py-2.5 first:pt-0 last:border-b-0 sm:grid-cols-[minmax(10rem,1fr)_auto_auto_auto_auto] sm:items-center">
                            <form class="contents" method="POST" action="{{ route('course-topics.update', $topic) }}">
                                @csrf
                                @method('PUT')
                                <label class="sr-only" for="topic-{{ $topic->id }}-name">Topic name</label>
                                <input class="min-w-0 rounded-md border border-pedago-line px-3 py-2 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="topic-{{ $topic->id }}-name" name="name" value="{{ $topic->name }}" maxlength="100" required>
                                <span @class([
                                    'inline-flex min-w-24 items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-pedago-tint text-pedago-blue-dark' => $topic->campaigns_count > 0,
                                    'bg-pedago-canvas text-pedago-muted' => $topic->campaigns_count === 0,
                                ])>
                                    @if ($topic->campaigns_count > 0)
                                        ● {{ $topic->campaigns_count }} {{ Str::plural('campaign', $topic->campaigns_count) }}
                                    @else
                                        ● Unused
                                    @endif
                                </span>
                                <button class="rounded-md border border-pedago-blue px-3 py-2 text-xs font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" type="submit">Save topic</button>
                            </form>
                            <form method="POST" action="{{ route('course-topics.destroy', $topic) }}" onsubmit="return confirm('Delete this topic? Topics used by courses cannot be deleted.')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="selected_subject_id" value="{{ $selectedSubject->id }}">
                                <button class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-pedago-red transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($topic->courses_count > 0 || $topic->campaigns_count > 0) title="{{ $topic->courses_count > 0 || $topic->campaigns_count > 0 ? 'A topic used by campaigns or courses cannot be deleted' : 'Delete topic' }}">Delete topic</button>
                            </form>
                        </div>
                    @empty
                        <p class="rounded-md bg-pedago-canvas px-3 py-4 text-sm text-pedago-muted">No topics yet. Add the first topic below.</p>
                    @endforelse

                    <form class="mt-3 grid gap-2 sm:grid-cols-[minmax(10rem,1fr)_auto] sm:items-end" method="POST" action="{{ route('course-topics.store') }}">
                        @csrf
                        <input type="hidden" name="subject_id" value="{{ $selectedSubject->id }}">
                        <label class="sr-only" for="new-topic-{{ $selectedSubject->id }}">New topic name</label>
                        <input class="min-w-0 rounded-md border border-pedago-line px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="new-topic-{{ $selectedSubject->id }}" name="name" maxlength="100" placeholder="New topic name" required>
                        <button class="rounded-md bg-pedago-blue px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark" type="submit">Add topic to {{ $selectedSubject->name }}</button>
                    </form>
                    @error('topic')
                        <p class="mt-2 text-xs text-pedago-red">{{ $message }}</p>
                    @enderror
                    @error('subject_id')
                        <p class="mt-2 text-xs text-pedago-red">{{ $message }}</p>
                    @enderror
                    @error('name')
                        <p class="mt-2 text-xs text-pedago-red">{{ $message }}</p>
                    @enderror
                </section>

                <section class="rounded-lg border border-pedago-line bg-white p-4 shadow-sm" aria-labelledby="lead-preview-heading">
                    <div class="mb-3 flex items-baseline justify-between gap-2">
                        <h2 class="text-sm font-bold" id="lead-preview-heading">How leads will see it</h2>
                        <span class="text-xs text-pedago-muted">Preview</span>
                    </div>
                    @if ($previewLead?->source?->course)
                        <p class="mb-3 text-xs leading-relaxed text-pedago-muted">Campaigns carry a subject and topic. Leads from those campaigns show the interest at a glance.</p>
                        <div class="flex flex-wrap items-center gap-2 rounded-md bg-pedago-canvas p-3 text-xs">
                            <span class="font-bold text-pedago-ink">{{ $previewLead->student_name }}</span>
                            <span class="text-pedago-muted">{{ $previewLead->contact_number }}</span>
                            <span class="rounded-full bg-pedago-tint px-2.5 py-1 font-semibold text-pedago-blue-dark">● {{ $previewLead->source->course->subject->name }}</span>
                            @if ($previewLead->source->course->topic)
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-semibold text-emerald-800">● {{ $previewLead->source->course->topic->name }}</span>
                            @endif
                            <span class="rounded-full bg-slate-200 px-2.5 py-1 font-semibold text-slate-700">● {{ $previewLead->source->campaign_name }}</span>
                        </div>
                    @else
                        <p class="text-xs leading-relaxed text-pedago-muted">Campaigns linked to a course under {{ $selectedSubject->name }} will show the subject and topic on each lead. No matching campaign leads are available to preview yet.</p>
                    @endif
                </section>
            </div>
        @else
            <section class="flex min-h-48 items-center justify-center rounded-lg border border-dashed border-pedago-line bg-white p-6 text-center text-sm text-pedago-muted">
                Add a subject to start building the catalogue.
            </section>
        @endif
    </div>
@endsection
