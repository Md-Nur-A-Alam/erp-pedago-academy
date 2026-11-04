<?php

namespace App\Http\Controllers;

use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DuplicateLeadReviewController extends Controller
{
    private const MERGEABLE_FIELDS = [
        'guardian_name',
        'whatsapp_number',
        'alternative_contact_number',
        'email',
        'location',
        'age',
        'class',
        'student_parent_type',
        'student_background',
        'ad_set_id',
        'interested_course_id',
    ];

    public function index(Request $request): View
    {
        $this->adminFor($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['Pending Review', 'Approved New', 'Merged', 'Discarded', 'all'])],
        ]);
        $status = $filters['status'] ?? 'Pending Review';

        return view('duplicate-lead-reviews.index', [
            'reviews' => DuplicateLeadReview::query()
                ->with(['matchedLead.currentOwner', 'reviewedBy'])
                ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
                ->when(
                    filled($filters['search'] ?? null),
                    function (Builder $query) use ($filters): void {
                        $search = '%'.trim($filters['search']).'%';
                        $query->where(function (Builder $query) use ($search): void {
                            $query->where('incoming_lead_data->student_name', 'like', $search)
                                ->orWhere('incoming_lead_data->contact_number', 'like', $search)
                                ->orWhereHas('matchedLead', function (Builder $matchedLeadQuery) use ($search): void {
                                    $matchedLeadQuery->where('student_name', 'like', $search)
                                        ->orWhere('contact_number', 'like', $search);
                                });
                        });
                    },
                )
                ->latest('created_at')
                ->paginate(10)
                ->withQueryString(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $status,
            ],
        ]);
    }

    public function update(Request $request, DuplicateLeadReview $duplicateLeadReview): RedirectResponse
    {
        $employee = $this->adminFor($request);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve_new', 'merge', 'discard'])],
        ]);

        DB::transaction(function () use ($duplicateLeadReview, $employee, $validated): void {
            $review = DuplicateLeadReview::query()
                ->whereKey($duplicateLeadReview->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($review->status !== 'Pending Review') {
                throw ValidationException::withMessages([
                    'review' => 'This duplicate review has already been completed.',
                ]);
            }

            if ($validated['decision'] === 'approve_new') {
                $incomingLeadData = $review->incoming_lead_data;
                foreach (['contact_number', 'whatsapp_number', 'alternative_contact_number'] as $phoneField) {
                    if (filled($incomingLeadData[$phoneField] ?? null)) {
                        $incomingLeadData[$phoneField] = User::normalizePhoneNumber((string) $incomingLeadData[$phoneField]);
                    }
                }

                $incomingLead = Validator::make($incomingLeadData, [
                    'source_id' => ['required', 'integer', 'exists:leads_source,source_id'],
                    'student_name' => ['required', 'string', 'max:150'],
                    'contact_number' => ['required', 'string', 'max:25', new PhoneNumber],
                    'guardian_name' => ['nullable', 'string', 'max:150'],
                    'whatsapp_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
                    'alternative_contact_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
                    'email' => ['nullable', 'email', 'max:120'],
                    'location' => ['nullable', 'string', 'max:255'],
                    'age' => ['nullable', 'integer', 'min:0', 'max:120'],
                    'class' => ['nullable', 'string', 'max:50'],
                    'student_parent_type' => ['nullable', 'string', 'max:30'],
                    'student_background' => ['nullable', 'string'],
                    'ad_set_id' => ['nullable', 'string', 'max:100'],
                    'interested_course_id' => ['nullable', 'integer', 'exists:courses,id'],
                ])->validate();

                Lead::query()->create([
                    ...$incomingLead,
                    'status' => 'New',
                    'edited_by' => $employee->id,
                ]);
            } elseif ($validated['decision'] === 'merge') {
                $matchedLead = Lead::query()
                    ->whereKey($review->matched_lead_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $updates = [];

                foreach (self::MERGEABLE_FIELDS as $field) {
                    $value = $review->incoming_lead_data[$field] ?? null;

                    if (filled($value) && blank($matchedLead->{$field})) {
                        $updates[$field] = $value;
                    }
                }

                $matchedLead->update([
                    ...$updates,
                    'edited_by' => $employee->id,
                ]);
            }

            $review->update([
                'status' => match ($validated['decision']) {
                    'approve_new' => 'Approved New',
                    'merge' => 'Merged',
                    default => 'Discarded',
                },
                'reviewed_by_employee_id' => $employee->id,
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->route('duplicate-lead-reviews.index')
            ->with('status', 'Duplicate review completed successfully.');
    }

    private function adminFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless($employee instanceof Employee && $employee->isAdmin(), 403);

        return $employee;
    }
}
