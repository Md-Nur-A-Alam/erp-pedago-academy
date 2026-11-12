<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseBatchController extends Controller
{
    public function index(Request $request): View
    {
        $this->adminFor($request);

        return view('course-batches.index', [
            'batches' => CourseBatch::query()
                ->with('course')
                ->withCount([
                    'enrollments as students_admitted' => fn ($query) => $query->reservingSeat(),
                ])
                ->orderByDesc('is_latest')
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->paginate(15),
            'courses' => Course::query()->orderBy('title')->get(['id', 'title', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->adminFor($request);
        $request->merge([
            'batch_name' => trim((string) $request->input('batch_name', '')),
            'short_code' => trim((string) $request->input('short_code', '')),
        ]);
        $validated = $request->validate($this->batchRules($request));

        DB::transaction(function () use ($validated): void {
            $course = Course::query()->whereKey($validated['course_id'])->lockForUpdate()->firstOrFail();
            $isLatest = (bool) ($validated['is_latest'] ?? false) || ! $course->batches()->exists();

            if ($isLatest) {
                $course->batches()->update(['is_latest' => false]);
            }

            $course->batches()->create([
                ...Arr::except($validated, ['course_id']),
                'is_latest' => $isLatest,
            ]);
        });

        return redirect()->route('course-batches.index')->with('status', 'Course batch created successfully.');
    }

    public function edit(Request $request, CourseBatch $courseBatch): View
    {
        $this->adminFor($request);
        $courseBatch->load('course:id,title,code');

        return view('course-batches.edit', ['courseBatch' => $courseBatch]);
    }

    public function update(Request $request, CourseBatch $courseBatch): RedirectResponse
    {
        $this->adminFor($request);
        $request->merge([
            'batch_name' => trim((string) $request->input('batch_name', '')),
            'short_code' => trim((string) $request->input('short_code', '')),
        ]);
        $validated = $request->validate($this->batchRules($request, $courseBatch));

        DB::transaction(function () use ($courseBatch, $validated): void {
            $course = Course::query()->whereKey($courseBatch->course_id)->lockForUpdate()->firstOrFail();
            $lockedBatch = CourseBatch::query()->whereKey($courseBatch->getKey())->lockForUpdate()->firstOrFail();

            $isLatest = (bool) ($validated['is_latest'] ?? false);

            if ($isLatest) {
                $course->batches()->where('id', '!=', $lockedBatch->getKey())->update(['is_latest' => false]);
            }

            $lockedBatch->update([...$validated, 'is_latest' => $isLatest]);
        });

        return redirect()->route('course-batches.index')->with('status', 'Course batch updated successfully.');
    }

    public function destroy(Request $request, CourseBatch $courseBatch): RedirectResponse
    {
        $this->adminFor($request);

        $hasEnrollments = DB::transaction(function () use ($courseBatch): bool {
            $course = Course::query()->whereKey($courseBatch->course_id)->lockForUpdate()->firstOrFail();
            $lockedBatch = CourseBatch::query()->whereKey($courseBatch->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedBatch->enrollments()->exists()) {
                return true;
            }

            $wasLatest = $lockedBatch->is_latest;
            $lockedBatch->delete();

            if ($wasLatest) {
                $course->batches()
                    ->orderByDesc('start_date')
                    ->orderByDesc('id')
                    ->first()
                    ?->update(['is_latest' => true]);
            }

            return false;
        });

        if ($hasEnrollments) {
            return back()->withErrors([
                'batch' => 'This batch cannot be deleted while enrollments reference it.',
            ]);
        }

        return redirect()->route('course-batches.index')->with('status', 'Course batch deleted successfully.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function batchRules(Request $request, ?CourseBatch $courseBatch = null): array
    {
        $courseId = $courseBatch?->course_id ?? $request->input('course_id');

        return [
            ...($courseBatch === null ? ['course_id' => ['required', 'integer', 'exists:courses,id']] : []),
            'batch_number' => [
                'required',
                'integer',
                'min:1',
                'max:999999',
                Rule::unique('course_batch', 'batch_number')
                    ->where(fn (Builder $query) => $query->where('course_id', $courseId))
                    ->ignore($courseBatch?->id),
            ],
            'batch_name' => ['required', 'string', 'max:100'],
            'short_code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('course_batch', 'short_code')
                    ->where(fn (Builder $query) => $query->where('course_id', $courseId))
                    ->ignore($courseBatch?->id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'start_date' => ['required', 'date'],
            'is_latest' => ['sometimes', 'boolean'],
        ];
    }

    private function adminFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless($employee instanceof Employee && $employee->isAdmin(), 403);

        return $employee;
    }
}
