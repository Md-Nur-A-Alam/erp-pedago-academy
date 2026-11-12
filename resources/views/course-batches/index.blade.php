@extends('layouts.app', ['title' => 'Course batches'])

@section('page-title', 'Course batches')
@section('page-description', 'Manage course cohorts, seat capacity, admissions, and start dates.')
@section('content')
    <div class="flex flex-col gap-6">
        <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="create-batch-heading">
            <div class="mb-5 flex flex-col gap-1">
                <h2 class="text-base font-bold" id="create-batch-heading">Add a course batch</h2>
                <p class="text-sm text-pedago-muted">Admissions count enrolled students assigned to this batch; vacancy is calculated from capacity.</p>
            </div>
            <form class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" method="POST" action="{{ route('course-batches.store') }}">
                @csrf
                <label class="flex flex-col gap-2 text-sm font-semibold" for="course_id">Course
                    <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="course_id" name="course_id" required>
                        <option value="">Select a course</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->title }} ({{ $course->code }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold" for="batch_number">Batch number
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="batch_number" name="batch_number" type="number" min="1" max="999999" value="{{ old('batch_number') }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold" for="batch_name">Batch name
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="batch_name" name="batch_name" value="{{ old('batch_name') }}" maxlength="100" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold" for="short_code">Short code
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal uppercase transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="short_code" name="short_code" value="{{ old('short_code') }}" maxlength="30" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold" for="capacity">Student capacity
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="capacity" name="capacity" type="number" min="1" max="100000" value="{{ old('capacity', 25) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold" for="start_date">Batch start date
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" required>
                </label>
                <label class="flex items-center gap-2 self-end rounded-md border border-pedago-line px-3 py-3 text-sm font-medium">
                    <input class="size-4 accent-pedago-blue" name="is_latest" type="checkbox" value="1" @checked(old('is_latest', '1'))>
                    Mark as latest batch
                </label>
                <div class="flex items-end">
                    <button class="w-full rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark sm:w-auto" type="submit">Add batch</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-pedago-line bg-white shadow-sm" aria-labelledby="batch-list-heading">
            <div class="border-b border-pedago-line px-5 py-4">
                <h2 class="text-base font-bold" id="batch-list-heading">All course batches</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead class="bg-pedago-canvas text-[11px] uppercase tracking-wide text-pedago-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Course / batch</th>
                            <th class="px-5 py-3 font-semibold">Short code</th>
                            <th class="px-5 py-3 font-semibold">Start date</th>
                            <th class="px-5 py-3 font-semibold">Capacity</th>
                            <th class="px-5 py-3 font-semibold">Admitted</th>
                            <th class="px-5 py-3 font-semibold">Vacancy</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pedago-line">
                        @forelse ($batches as $batch)
                            <tr class="transition-colors hover:bg-pedago-canvas/70">
                                <td class="px-5 py-3">
                                    <p class="font-semibold">{{ $batch->course->title }}</p>
                                    <p class="mt-0.5 text-xs text-pedago-muted">{{ $batch->batch_name }} · Batch {{ $batch->batch_number }}</p>
                                </td>
                                <td class="px-5 py-3">{{ $batch->short_code }}</td>
                                <td class="px-5 py-3">{{ $batch->start_date->format('M j, Y') }}</td>
                                <td class="px-5 py-3">{{ number_format($batch->capacity) }}</td>
                                <td class="px-5 py-3">{{ number_format($batch->students_admitted) }}</td>
                                <td class="px-5 py-3">{{ number_format($batch->vacancy) }}</td>
                                <td class="px-5 py-3">
                                    @if ($batch->is_latest)
                                        <span class="rounded-full bg-pedago-green-tint px-2.5 py-1 text-xs font-semibold text-pedago-green">Latest</span>
                                    @else
                                        <span class="text-xs text-pedago-muted">Previous</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('course-batches.edit', $batch) }}">Edit</a>
                                        <form method="POST" action="{{ route('course-batches.destroy', $batch) }}" onsubmit="return confirm('Delete this batch? Batches with enrollments cannot be deleted.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="font-semibold text-pedago-red underline-offset-2 hover:underline" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-10 text-center text-sm text-pedago-muted" colspan="8">No course batches have been added.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($batches->hasPages())
                <div class="border-t border-pedago-line px-5 py-4">{{ $batches->links() }}</div>
            @endif
        </section>
    </div>
@endsection
