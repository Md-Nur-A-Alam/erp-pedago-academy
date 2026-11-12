@extends('layouts.app', ['title' => 'Courses'])

@section('page-title', 'Courses')
@section('page-description', 'Manage course details, pricing, and availability.')
@section('page-actions')
    <span class="hidden rounded-full border border-pedago-line bg-white px-3 py-1.5 text-xs font-semibold text-pedago-muted sm:inline-flex">{{ number_format($courses->total()) }} courses</span>
    <button class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="button" data-open-dialog="course-create-modal">
        + Add course
    </button>
@endsection

@section('content')
    <section class="overflow-hidden rounded-xl border border-pedago-line bg-white shadow-sm" aria-labelledby="course-list-heading">
        <div class="border-b border-pedago-line px-5 py-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-base font-bold" id="course-list-heading">Course catalogue</h2>
                <a class="text-sm font-semibold text-pedago-blue-dark underline underline-offset-2" href="{{ route('courses.index') }}">Clear filters</a>
            </div>
            <form class="mt-4 grid gap-3 sm:grid-cols-[minmax(14rem,1fr)_12rem_auto]" method="GET" action="{{ route('courses.index') }}">
                <label class="sr-only" for="course-search">Search courses</label>
                <input class="rounded-md border border-pedago-line px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="course-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search title, code, or category">
                <label class="sr-only" for="course-status">Filter by availability</label>
                <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="course-status" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
                <button class="rounded-md border border-pedago-line px-4 py-2.5 text-sm font-semibold transition hover:border-pedago-blue hover:bg-pedago-tint" type="submit">Filter courses</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="bg-pedago-canvas text-[11px] uppercase tracking-wide text-pedago-muted">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Course</th>
                        <th class="px-5 py-3 font-semibold">Subject</th>
                        <th class="px-5 py-3 font-semibold">Version</th>
                        <th class="px-5 py-3 font-semibold">Base fee</th>
                        <th class="px-5 py-3 font-semibold">Leads</th>
                        <th class="px-5 py-3 font-semibold">Enrollments</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pedago-line">
                    @forelse ($courses as $course)
                        <tr class="transition-colors hover:bg-pedago-canvas/70">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-pedago-ink">{{ $course->title }}</p>
                                <p class="mt-0.5 text-xs text-pedago-muted">{{ $course->code }}{{ $course->category ? ' · '.$course->category : '' }}</p>
                            </td>
                            <td class="px-5 py-3">{{ $course->subject?->name }}{{ $course->topic ? ' · '.$course->topic->name : '' }}</td>
                            <td class="px-5 py-3">{{ $course->version }}</td>
                            <td class="px-5 py-3 font-mono">৳ {{ number_format((float) $course->base_fee, 2) }}</td>
                            <td class="px-5 py-3">{{ number_format($course->leads_count) }}</td>
                            <td class="px-5 py-3">{{ number_format($course->enrollments_count) }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-pedago-green-tint text-pedago-green' => $course->is_active,
                                    'bg-pedago-canvas text-pedago-muted' => ! $course->is_active,
                                ])>{{ $course->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <a class="inline-flex size-7 items-center justify-center rounded-md border border-pedago-blue text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('courses.edit', $course) }}" title="Edit course" aria-label="Edit {{ $course->title }}">
                                        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                            <path d="M3.5 5.75c0-.414.336-.75.75-.75H10a.75.75 0 010 1.5H4.25v9h9V10a.75.75 0 011.5 0v5.75c0 .966-.784 1.75-1.75 1.75h-9A1.75 1.75 0 012.5 15.75v-9c0-.966.784-1.75 1.75-1.75z" />
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Delete this course? Referenced courses cannot be deleted.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex size-7 items-center justify-center rounded-md border border-rose-200 text-rose-700 transition hover:bg-rose-50" type="submit" title="Delete course" aria-label="Delete {{ $course->title }}">
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-10 text-center text-sm text-pedago-muted" colspan="8">No courses match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($courses->hasPages())
            <div class="border-t border-pedago-line px-5 py-4">{{ $courses->links() }}</div>
        @endif
    </section>

    <dialog id="course-create-modal" class="m-auto max-h-[92vh] w-[min(94vw,40rem)] overflow-y-auto rounded-xl border border-pedago-line bg-white p-0 text-pedago-ink shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between border-b border-pedago-line px-5 py-4">
            <div>
                <h2 class="text-sm font-bold" id="create-course-heading">Add a course</h2>
                <p class="text-xs text-pedago-muted">New courses are active by default. Existing lead and enrollment records are preserved.</p>
            </div>
            <button class="rounded p-1 text-pedago-muted hover:bg-pedago-canvas hover:text-pedago-ink" type="button" data-close-dialog aria-label="Close">×</button>
        </div>
        <form class="grid gap-4 p-5 sm:grid-cols-2" method="POST" action="{{ route('courses.store') }}" id="course-create-form">
            @csrf
            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="title">Course title <span class="text-pedago-red">*</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="title" name="title" value="{{ old('title') }}" maxlength="150" required placeholder="e.g. Spoken English Mastery">
                @error('title')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="code">Course code <span class="text-pedago-red">*</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal uppercase focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="code" name="code" value="{{ old('code') }}" maxlength="50" required placeholder="e.g. ENG-SPOKEN">
                @error('code')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            @include('courses._taxonomy-fields')

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="version">Version <span class="text-pedago-red">*</span>
                <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="version" name="version" required>
                    @foreach ($versions as $version)
                        <option value="{{ $version }}" @selected(old('version', 'Bangla') === $version)>{{ $version }}</option>
                    @endforeach
                </select>
                @error('version')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold" for="category">Category <span class="font-normal text-pedago-muted">(optional)</span>
                <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="category" name="category" value="{{ old('category') }}" maxlength="100" placeholder="e.g. Language">
                @error('category')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold sm:col-span-2" for="base_fee">Base fee <span class="text-pedago-red">*</span>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-pedago-muted">৳</span>
                    <input class="w-full rounded-md border border-pedago-line bg-white py-2 pl-8 pr-3 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="base_fee" name="base_fee" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('base_fee', '0.00') }}" required>
                </div>
                @error('base_fee')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold sm:col-span-2" for="description">Description <span class="font-normal text-pedago-muted">(optional)</span>
                <textarea class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="description" name="description" rows="2" maxlength="10000" placeholder="Course outline, goals, and target audience...">{{ old('description') }}</textarea>
                @error('description')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold sm:col-span-2" for="conditions">Enrollment conditions <span class="font-normal text-pedago-muted">(optional)</span>
                <textarea class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="conditions" name="conditions" rows="2" maxlength="10000" placeholder="e.g. Placement test required">{{ old('conditions') }}</textarea>
                @error('conditions')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
            </label>

            <div class="flex items-center justify-between border-t border-pedago-line pt-3 sm:col-span-2">
                <span class="text-xs font-semibold">Availability</span>
                <input name="is_active" type="hidden" value="0">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-pedago-line px-2.5 py-1.5 text-xs font-semibold text-pedago-green">
                    <input class="size-4 accent-pedago-blue" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                    Active and available
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-pedago-line pt-4 sm:col-span-2">
                <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold text-pedago-ink hover:bg-pedago-canvas" type="button" data-close-dialog>Cancel</button>
                <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white hover:bg-pedago-blue-dark" type="submit">Add course</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        const courseModal = document.getElementById('course-create-modal');

        document.querySelectorAll('[data-open-dialog="course-create-modal"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                courseModal.showModal();
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach((btn) => {
            btn.addEventListener('click', () => {
                btn.closest('dialog')?.close();
            });
        });

        courseModal?.addEventListener('click', (event) => {
            if (event.target === courseModal) {
                courseModal.close();
            }
        });

        if (window.location.hash === '#course-create' || new URLSearchParams(window.location.search).get('create') === '1' || @js($errors->any())) {
            courseModal?.showModal();
        }
    </script>
@endpush
