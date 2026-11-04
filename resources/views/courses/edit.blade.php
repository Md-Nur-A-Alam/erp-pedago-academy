@extends('layouts.app', ['title' => 'Edit course'])

@section('page-title', 'Edit course')
@section('page-description', 'Update course details while preserving existing lead and enrollment records.')
@section('page-actions')
    <a class="rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('courses.index') }}">Courses</a>
@endsection

@section('content')
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <form class="grid gap-5 rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:grid-cols-2 sm:p-7" method="POST" action="{{ route('courses.update', $course) }}">
            @csrf
            @method('PUT')
            <label class="flex flex-col gap-2 text-sm font-semibold">Course title
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="title" value="{{ old('title', $course->title) }}" maxlength="150" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold">Course code
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal uppercase transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="code" value="{{ old('code', $course->code) }}" maxlength="50" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold">Subject
                <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="subject" required>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject }}" @selected(old('subject', $course->subject) === $subject)>{{ $subject }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold">Version
                <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="version" required>
                    @foreach ($versions as $version)
                        <option value="{{ $version }}" @selected(old('version', $course->version) === $version)>{{ $version }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold">Category
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="category" value="{{ old('category', $course->category) }}" maxlength="100">
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold">Base fee
                <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="base_fee" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('base_fee', $course->base_fee) }}" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold sm:col-span-2">Description
                <textarea class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="description" rows="3" maxlength="10000">{{ old('description', $course->description) }}</textarea>
            </label>
            <label class="flex flex-col gap-2 text-sm font-semibold sm:col-span-2">Enrollment conditions
                <textarea class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="conditions" rows="3" maxlength="10000">{{ old('conditions', $course->conditions) }}</textarea>
            </label>
            <label class="flex items-center gap-2 rounded-md border border-pedago-line px-3 py-3 text-sm font-medium sm:col-span-2">
                <input class="size-4 accent-pedago-blue" name="is_active" type="checkbox" value="1" @checked(old('is_active', $course->is_active))>
                Active and available
            </label>
            <div class="flex flex-wrap gap-3 sm:col-span-2">
                <button class="rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">Save changes</button>
                <a class="rounded-md border border-pedago-line px-5 py-2.5 text-sm font-semibold transition hover:bg-pedago-canvas" href="{{ route('courses.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
