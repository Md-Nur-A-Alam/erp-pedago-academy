@extends('layouts.app', ['title' => 'Edit course batch'])

@section('page-title', 'Edit course batch')
@section('page-description', 'Update the batch details and its latest-batch designation.')
@section('page-actions')
    <a class="rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('course-batches.index') }}">Course batches</a>
@endsection
@section('content')
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <form class="grid gap-5 rounded-xl border border-pedago-line bg-white p-5 shadow-sm sm:grid-cols-2 sm:p-7" method="POST" action="{{ route('course-batches.update', $courseBatch) }}">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-2 text-sm font-semibold">
                Course
                <p class="rounded-md border border-pedago-line bg-pedago-canvas px-3 py-2.5 font-normal">{{ $courseBatch->course->title }} ({{ $courseBatch->course->code }})</p>
            </div>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="batch_number">Batch number
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal" id="batch_number" name="batch_number" type="number" min="1" max="999999" value="{{ old('batch_number', $courseBatch->batch_number) }}" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="batch_name">Batch name
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal" id="batch_name" name="batch_name" value="{{ old('batch_name', $courseBatch->batch_name) }}" maxlength="100" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="short_code">Short code
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal uppercase" id="short_code" name="short_code" value="{{ old('short_code', $courseBatch->short_code) }}" maxlength="30" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="capacity">Student capacity
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal" id="capacity" name="capacity" type="number" min="1" max="100000" value="{{ old('capacity', $courseBatch->capacity) }}" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold" for="start_date">Batch start date
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal" id="start_date" name="start_date" type="date" value="{{ old('start_date', $courseBatch->start_date->toDateString()) }}" required>
            </label>
            <label class="flex items-center gap-2 rounded-md border border-pedago-line px-3 py-3 text-sm font-medium sm:col-span-2">
                <input class="size-4 accent-pedago-blue" name="is_latest" type="checkbox" value="1" @checked(old('is_latest', $courseBatch->is_latest))>
                Mark as latest batch
            </label>
            <div class="flex flex-wrap gap-3 sm:col-span-2">
                <button class="rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark" type="submit">Save changes</button>
                <a class="rounded-md border border-pedago-line px-5 py-2.5 text-sm font-semibold transition hover:bg-pedago-canvas" href="{{ route('course-batches.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
