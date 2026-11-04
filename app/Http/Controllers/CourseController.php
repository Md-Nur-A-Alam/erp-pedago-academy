<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    private const SUBJECTS = ['English', 'Bangla', 'Mathematics', 'Science', 'Programming', 'General'];

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
                ->withCount(['leads', 'enrollments'])
                ->orderBy('title')
                ->paginate(15)
                ->withQueryString(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'subjects' => self::SUBJECTS,
            'versions' => self::VERSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->adminFor($request);
        $validated = $request->validate($this->courseRules());

        Course::query()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'edited_by' => $employee->id,
        ]);

        return redirect()->route('courses.index')->with('status', 'Course created successfully.');
    }

    public function edit(Request $request, Course $course): View
    {
        $this->adminFor($request);

        return view('courses.edit', [
            'course' => $course,
            'subjects' => self::SUBJECTS,
            'versions' => self::VERSIONS,
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $employee = $this->adminFor($request);
        $validated = $request->validate($this->courseRules($course));

        $course->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'edited_by' => $employee->id,
        ]);

        return redirect()->route('courses.index')->with('status', 'Course updated successfully.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $this->adminFor($request);
        $hasReferences = DB::transaction(function () use ($course): bool {
            $lockedCourse = Course::query()->whereKey($course->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedCourse->leads()->exists() || $lockedCourse->enrollments()->exists()) {
                return true;
            }

            $lockedCourse->delete();

            return false;
        });

        if ($hasReferences) {
            return back()->withErrors([
                'course' => 'This course cannot be deleted while leads or enrollments reference it. Deactivate it instead.',
            ]);
        }

        return redirect()->route('courses.index')->with('status', 'Course deleted successfully.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function courseRules(?Course $course = null): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('courses', 'code')->ignore($course?->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'conditions' => ['nullable', 'string', 'max:10000'],
            'subject' => ['required', Rule::in(self::SUBJECTS)],
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
}
