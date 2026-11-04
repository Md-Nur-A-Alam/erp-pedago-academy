<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReviewEmployeeChangeRequest
{
    public function approve(
        EmployeeChangeRequest $changeRequest,
        Employee $approver,
        ?string $reviewNote = null,
    ): EmployeeChangeRequest {
        return $this->review($changeRequest, $approver, 'Approved', $reviewNote);
    }

    public function reject(
        EmployeeChangeRequest $changeRequest,
        Employee $approver,
        ?string $reviewNote = null,
    ): EmployeeChangeRequest {
        return $this->review($changeRequest, $approver, 'Rejected', $reviewNote);
    }

    private function review(
        EmployeeChangeRequest $changeRequest,
        Employee $approver,
        string $status,
        ?string $reviewNote,
    ): EmployeeChangeRequest {
        return DB::transaction(function () use ($changeRequest, $approver, $status, $reviewNote): EmployeeChangeRequest {
            $lockedRequest = EmployeeChangeRequest::query()
                ->whereKey($changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $employee = Employee::query()
                ->whereKey($lockedRequest->employee_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $employee->canBeReviewedBy($approver)) {
                abort(403);
            }

            if ($lockedRequest->status !== 'Pending') {
                throw ValidationException::withMessages([
                    'request' => 'This change request has already been reviewed.',
                ]);
            }

            if ($status === 'Approved') {
                $changes = $lockedRequest->requested_changes;
                $unexpectedFields = array_diff(array_keys($changes), EmployeeChangeRequest::APPROVAL_FIELDS);

                if ($unexpectedFields !== []) {
                    throw ValidationException::withMessages([
                        'request' => 'This request contains unsupported employee fields.',
                    ]);
                }

                $phoneNumber = $changes['phone_number'] ?? null;
                unset($changes['phone_number']);

                if ($phoneNumber !== null) {
                    $phoneNumber = User::normalizePhoneNumber((string) $phoneNumber);
                    Validator::make(
                        ['phone_number' => $phoneNumber],
                        ['phone_number' => ['required', 'string', 'max:25', new PhoneNumber]],
                    )->validate();
                }

                $employee->fill($changes);
                $employee->edited_by = $approver->id;
                $employee->save();

                if ($phoneNumber !== null) {
                    $employee->user()->lockForUpdate()->firstOrFail()->update([
                        'phone_number' => $phoneNumber,
                    ]);
                }
            }

            $lockedRequest->update([
                'status' => $status,
                'reviewed_by_employee_id' => $approver->id,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
            ]);

            return $lockedRequest->refresh();
        });
    }
}
