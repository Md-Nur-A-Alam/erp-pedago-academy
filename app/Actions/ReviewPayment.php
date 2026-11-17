<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\InstallmentSchedule;
use App\Models\Lead;
use App\Models\LeadBatchItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewPayment
{
    /**
     * Verify a payment: the installment is paid, the student is enrolled, the lead is converted and
     * the executive's batch slot is released.
     */
    public function verify(Payment $payment, Employee $reviewer): Payment
    {
        return DB::transaction(function () use ($payment, $reviewer): Payment {
            $locked = $this->lockPending($payment);
            $locked->update([
                'status' => 'Verified',
                'verified_by_employee_id' => $reviewer->id,
                'verified_date' => now(),
                'rejection_reason' => null,
            ]);

            if ($locked->installment_schedule_id !== null) {
                InstallmentSchedule::query()
                    ->whereKey($locked->installment_schedule_id)
                    ->update(['status' => 'Paid', 'paid_at' => now()]);
            }

            $enrollment = Enrollment::query()->whereKey($locked->enrollment_id)->lockForUpdate()->firstOrFail();
            if ($enrollment->status !== 'Cancelled') {
                $enrollment->update(['status' => 'Enrolled']);

                Lead::query()
                    ->whereKey($enrollment->lead_id)
                    ->update(['status' => 'Converted', 'last_action_at' => now(), 'edited_by' => $reviewer->id]);
                LeadBatchItem::completeOpenForLead($enrollment->lead_id, 'Converted');
            }

            return $locked;
        });
    }

    /**
     * Reject a payment with a reason and put the installment, enrollment and lead back to awaiting payment.
     */
    public function reject(Payment $payment, Employee $reviewer, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reviewer, $reason): Payment {
            $locked = $this->lockPending($payment);
            $locked->update([
                'status' => 'Rejected',
                'verified_by_employee_id' => $reviewer->id,
                'verified_date' => now(),
                'rejection_reason' => $reason,
            ]);

            if ($locked->installment_schedule_id !== null) {
                $installment = InstallmentSchedule::query()->whereKey($locked->installment_schedule_id)->first();
                if ($installment !== null && $installment->status === 'Under Review') {
                    $installment->update(['status' => $installment->due_date->isPast() && ! $installment->due_date->isToday() ? 'Overdue' : 'Pending']);
                }
            }

            $enrollment = Enrollment::query()->whereKey($locked->enrollment_id)->lockForUpdate()->firstOrFail();
            $hasOtherPayments = Payment::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('status', ['Pending', 'Verified'])
                ->exists();

            if ($enrollment->status === 'Payment Under Review' && ! $hasOtherPayments) {
                $enrollment->update(['status' => 'Pending Payment']);
                Lead::query()
                    ->whereKey($enrollment->lead_id)
                    ->where('status', 'Payment Under Review')
                    ->update(['status' => 'Enrolled - Pending Payment', 'edited_by' => $reviewer->id]);
            }

            return $locked;
        });
    }

    private function lockPending(Payment $payment): Payment
    {
        $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'Pending') {
            throw ValidationException::withMessages([
                'payment' => 'This payment has already been '.strtolower($locked->status).'.',
            ]);
        }

        return $locked;
    }
}
