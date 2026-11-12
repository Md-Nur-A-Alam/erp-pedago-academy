<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->adminFor($request);
        $subjects = Subject::query()
            ->with([
                'topics' => fn ($query) => $query->orderBy('name')->withCount(['courses', 'campaigns']),
            ])
            ->withCount(['topics', 'courses', 'campaigns'])
            ->orderBy('name')
            ->get();
        $selectedSubject = $subjects->firstWhere('id', $request->integer('subject')) ?? $subjects->first();
        $previewLead = $selectedSubject === null
            ? null
            : Lead::query()
                ->with(['source.course.subject', 'source.course.topic'])
                ->whereHas('source.course', fn ($query) => $query->where('subject_id', $selectedSubject->id))
                ->orderByDesc('id')
                ->first();

        return view('subjects.index', [
            'subjects' => $subjects,
            'selectedSubject' => $selectedSubject,
            'previewLead' => $previewLead,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->adminFor($request);
        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')],
        ]);

        $subject = Subject::query()->create($validated);

        return redirect()->route('subjects.index', ['subject' => $subject->id])
            ->with('status', 'Subject created successfully.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $this->adminFor($request);
        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')->ignore($subject->id)],
        ]);

        $subject->update($validated);

        return redirect()->route('subjects.index', ['subject' => $request->integer('selected_subject_id', $subject->id)])
            ->with('status', 'Subject updated successfully.');
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        $this->adminFor($request);

        $hasCourses = DB::transaction(function () use ($subject): bool {
            $lockedSubject = Subject::query()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            if (
                $lockedSubject->courses()->exists()
                || $lockedSubject->campaigns()->exists()
                || $lockedSubject->topics()->whereHas('courses')->exists()
            ) {
                return true;
            }

            $lockedSubject->delete();

            return false;
        });

        if ($hasCourses) {
            return back()->withErrors([
                'subject' => 'This subject cannot be deleted while courses or campaigns use it. Remove or reassign those records first.',
            ]);
        }

        return redirect()->route('subjects.index', [
            'subject' => $request->integer('selected_subject_id') ?: null,
        ])->with('status', 'Subject deleted successfully.');
    }

    private function adminFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless($employee instanceof Employee && $employee->isAdmin(), 403);

        return $employee;
    }
}
