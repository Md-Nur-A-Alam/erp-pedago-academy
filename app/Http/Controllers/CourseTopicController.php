<?php

namespace App\Http\Controllers;

use App\Models\CourseTopic;
use App\Models\Employee;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseTopicController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->adminFor($request);
        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('course_topics', 'name')->where(
                    fn (Builder $query) => $query->where('subject_id', $request->input('subject_id')),
                ),
            ],
        ]);

        Subject::query()->findOrFail($validated['subject_id'])->topics()->create(['name' => $validated['name']]);

        return redirect()->route('subjects.index', ['subject' => $validated['subject_id']])
            ->with('status', 'Topic created successfully.');
    }

    public function update(Request $request, CourseTopic $courseTopic): RedirectResponse
    {
        $this->adminFor($request);
        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('course_topics', 'name')
                    ->where(fn (Builder $query) => $query->where('subject_id', $courseTopic->subject_id))
                    ->ignore($courseTopic->id),
            ],
        ]);

        $courseTopic->update($validated);

        return redirect()->route('subjects.index', ['subject' => $courseTopic->subject_id])
            ->with('status', 'Topic updated successfully.');
    }

    public function destroy(Request $request, CourseTopic $courseTopic): RedirectResponse
    {
        $this->adminFor($request);

        $hasCourses = DB::transaction(function () use ($courseTopic): bool {
            $lockedTopic = CourseTopic::query()->whereKey($courseTopic->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedTopic->courses()->exists()) {
                return true;
            }

            $lockedTopic->delete();

            return false;
        });

        if ($hasCourses) {
            return back()->withErrors([
                'topic' => 'This topic cannot be deleted while courses use it. Reassign those courses first.',
            ]);
        }

        return redirect()->route('subjects.index', [
            'subject' => $request->integer('selected_subject_id') ?: $courseTopic->subject_id,
        ])->with('status', 'Topic deleted successfully.');
    }

    private function adminFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless($employee instanceof Employee && $employee->isAdmin(), 403);

        return $employee;
    }
}
