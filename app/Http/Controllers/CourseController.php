<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseTopic;
use App\Models\Employee;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    private const VERSIONS = ['Bangla', 'English'];

    public function index(Request $request): View
    {
        $this->adminFor($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        return view('courses.index', [
            'courses' => Course::query()
                ->when(
                    filled($filters['search'] ?? null),
                    fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                        $search = '%'.trim($filters['search']).'%';
                        $query->where('title', 'like', $search)
                            ->orWhere('code', 'like', $search)
                            ->orWhere('category', 'like', $search);
                    }),
                )
                ->when(
                    filled($filters['status'] ?? null),
                    fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'),
                )
                ->with(['subject', 'topic'])
                ->withCount(['leads', 'enrollments'])
                ->orderBy('title')
                ->paginate(15)
                ->withQueryString(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'subjects' => $this->subjectsWithTopics(),
            'versions' => self::VERSIONS,
            'course' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->adminFor($request);
        $request->merge([
            'subject_name' => trim((string) $request->input('subject_name', '')),
            'topic_name' => trim((string) $request->input('topic_name', '')),
        ]);
        $validated = $request->validate($this->courseRules($request));

        DB::transaction(function () use ($validated, $request, $employee): void {
            [$subject, $topic] = $this->resolveSubjectAndTopic($validated);
            $attributes = Arr::except($validated, ['subject_name', 'topic_id', 'topic_name']);
            $attributes['subject_id'] = $subject->id;
            $attributes['course_topic_id'] = $topic->id;
            $attributes['is_active'] = $request->boolean('is_active');
            $attributes['edited_by'] = $employee->id;

            Course::query()->create($attributes);
        });

        return redirect()->route('courses.index')->with('status', 'Course created successfully.');
    }

    public function edit(Request $request, Course $course): View
    {
        $this->adminFor($request);
        $course->load(['subject', 'topic']);

        return view('courses.edit', [
            'course' => $course,
            'subjects' => $this->subjectsWithTopics(),
            'versions' => self::VERSIONS,
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $employee = $this->adminFor($request);
        $request->merge([
            'subject_name' => trim((string) $request->input('subject_name', '')),
            'topic_name' => trim((string) $request->input('topic_name', '')),
        ]);
        $validated = $request->validate($this->courseRules($request, $course));

        DB::transaction(function () use ($course, $validated, $request, $employee): void {
            [$subject, $topic] = $this->resolveSubjectAndTopic($validated);
            $attributes = Arr::except($validated, ['subject_name', 'topic_id', 'topic_name']);
            $attributes['subject_id'] = $subject->id;
            $attributes['course_topic_id'] = $topic->id;
            $attributes['is_active'] = $request->boolean('is_active');
            $attributes['edited_by'] = $employee->id;

            $course->update($attributes);
        });

        return redirect()->route('courses.index')->with('status', 'Course updated successfully.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $this->adminFor($request);
        $hasReferences = DB::transaction(function () use ($course): bool {
            $lockedCourse = Course::query()->whereKey($course->getKey())->lockForUpdate()->firstOrFail();

            if (
                $lockedCourse->leads()->exists()
                || $lockedCourse->enrollments()->exists()
                || $lockedCourse->batches()->exists()
                || $lockedCourse->campaigns()->exists()
            ) {
                return true;
            }

            $lockedCourse->delete();

            return false;
        });

        if ($hasReferences) {
            return back()->withErrors([
                'course' => 'This course cannot be deleted while leads, enrollments, batches, or campaigns reference it. Deactivate it instead.',
            ]);
        }

        return redirect()->route('courses.index')->with('status', 'Course deleted successfully.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function courseRules(Request $request, ?Course $course = null): array
    {
        $isNewSubject = $request->input('subject_id') === 'other';
        $isNewTopic = $request->input('topic_id') === 'other';

        return [
            'title' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('courses', 'code')->ignore($course?->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'conditions' => ['nullable', 'string', 'max:10000'],
            'subject_id' => $isNewSubject
                ? ['required', Rule::in(['other'])]
                : ['required', 'integer', 'exists:subjects,id'],
            'subject_name' => $isNewSubject
                ? ['required', 'string', 'max:100', Rule::unique('subjects', 'name')]
                : ['nullable', 'string', 'max:100'],
            'topic_id' => $isNewTopic
                ? ['required', Rule::in(['other'])]
                : [
                    'required',
                    Rule::exists('course_topics', 'id')->where(
                        fn (\Illuminate\Database\Query\Builder $query) => $query->where('subject_id', $request->input('subject_id')),
                    ),
                ],
            'topic_name' => $isNewTopic
                ? ['required', 'string', 'max:100']
                : ['nullable', 'string', 'max:100'],
            'version' => ['required', Rule::in(self::VERSIONS)],
            'category' => ['nullable', 'string', 'max:100'],
            'base_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function adminFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless($employee instanceof Employee && $employee->isAdmin(), 403);

        return $employee;
    }

    /**
     * @return Collection<int, Subject>
     */
    private function subjectsWithTopics(): Collection
    {
        return Subject::query()
            ->with(['topics' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Subject, 1: CourseTopic}
     */
    private function resolveSubjectAndTopic(array $validated): array
    {
        $subject = $validated['subject_id'] === 'other'
            ? Subject::query()->create(['name' => $validated['subject_name']])
            : Subject::query()->findOrFail($validated['subject_id']);

        $topic = $validated['topic_id'] === 'other'
            ? $subject->topics()->firstOrCreate(['name' => $validated['topic_name']])
            : $subject->topics()->findOrFail($validated['topic_id']);

        return [$subject, $topic];
    }
}
