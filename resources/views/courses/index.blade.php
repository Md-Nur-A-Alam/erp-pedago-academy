@extends('layouts.app', ['title' => 'Courses'])

@section('page-title', 'Courses')
@section('page-description', 'Manage course details, pricing, and availability.')
@section('page-actions')
    <span class="hidden rounded-full border border-pedago-line bg-white px-3 py-1.5 text-xs font-semibold text-pedago-muted sm:inline-flex">{{ number_format($courses->total()) }} courses</span>
    <a class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" href="#course-create">Add course</a>
@endsection

@section('content')
    <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6" id="course-create" aria-labelledby="create-course-heading">
        <div class="mb-5 flex flex-col gap-1">
            <h2 class="text-base font-bold" id="create-course-heading">Add a course</h2>
            <p class="text-sm text-pedago-muted">New courses are active by default. Existing lead and enrollment records are preserved.</p>
        </div>
        <form class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" method="POST" action="{{ route('courses.store') }}">
            @csrf
            <label class="flex flex-col gap-2 text-sm font-semibold" for="title">Course title
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="title" name="title" value="{{ old('title') }}" maxlength="150" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="code">Course code
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal uppercase transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="code" name="code" value="{{ old('code') }}" maxlength="50" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="subject">Subject
                <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="subject" name="subject" required>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject }}" @selected(old('subject', 'General') === $subject)>{{ $subject }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="version">Version
                <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="version" name="version" required>
                    @foreach ($versions as $version)
                        <option value="{{ $version }}" @selected(old('version', 'Bangla') === $version)>{{ $version }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="category">Category <span class="font-normal text-pedago-muted">(optional)</span>
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="category" name="category" value="{{ old('category') }}" maxlength="100">
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="base_fee">Base fee
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="base_fee" name="base_fee" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('base_fee', '0.00') }}" required>
            </label>
            <label class="flex items-center gap-2 self-end rounded-md border border-pedago-line px-3 py-3 text-sm font-medium">
                <input class="size-4 accent-pedago-blue" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1'))>
                Active and available
            </label>
            <div class="flex items-end">
                <button class="w-full rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue sm:w-auto" type="submit">Add course</button>
            </div>
            <label class="flex flex-col gap-2 text-sm font-semibold sm:col-span-2 xl:col-span-2" for="description">Description <span class="font-normal text-pedago-muted">(optional)</span>
                <textarea class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="description" name="description" rows="2" maxlength="10000">{{ old('description') }}</textarea>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold sm:col-span-2 xl:col-span-2" for="conditions">Enrollment conditions <span class="font-normal text-pedago-muted">(optional)</span>
                <textarea class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="conditions" name="conditions" rows="2" maxlength="10000">{{ old('conditions') }}</textarea>
            </label>
        </form>
    </section>

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
                            <td class="px-5 py-3">{{ $course->subject }}</td>
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
                                <div class="flex items-center gap-3">
                                    <a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('courses.edit', $course) }}">Edit</a>
                                    <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Delete this course? Referenced courses cannot be deleted.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-semibold text-pedago-red underline-offset-2 hover:underline" type="submit">Delete</button>
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
@endsection
