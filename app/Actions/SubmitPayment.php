<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records that a student has paid an installment, with the transaction ID or the slip, and sends it to Accounts.
 * The money counts only after Accounts verifies it (see ReviewPayment).
 */
class SubmitPayment
{
    public const METHODS = ['bKash', 'Manual MFS', 'Manual Bank'];

    /**
     * @param  array{installment_schedule_id: int, amount: int|float|string, payment_method: string, transaction_id?: ?string, payment_date?: ?string}  $data
     */
    public function handle(Enrollment $enrollment, array $data, ?UploadedFile $slip = null): Payment
    {
        if (blank($data['transaction_id'] ?? null) && $slip === null) {
            throw ValidationException::withMessages(['transaction_id' => 'Give the transaction ID or attach the slip so Accounts can check the payment.']);
        }

        return DB::transaction(function () use ($enrollment, $data, $slip): Payment {
            $locked = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'Cancelled') {
                throw ValidationException::withMessages(['installment_schedule_id' => 'This enrollment was cancelled.']);
            }

            $installment = $locked->installmentSchedules()->lockForUpdate()->find($data['installment_schedule_id']);

            if ($installment === null || ! in_array($installment->status, ['Pending', 'Overdue'], true)) {
                throw ValidationException::withMessages(['installment_schedule_id' => 'That installment is already paid or is waiting to be verified.']);
            }

            if (abs((float) $data['amount'] - (float) $installment->due_amount) > 0.009) {
                throw ValidationException::withMessages(['amount' => 'The amount must equal the installment: '.number_format((float) $installment->due_amount, 2).'.']);
            }

            $payment = Payment::query()->create([
                'enrollment_id' => $locked->id,
                'installment_schedule_id' => $installment->id,
                'amount' => $installment->due_amount,
                'payment_date' => $data['payment_date'] ?? now(),
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'slip_screenshot_path' => $slip?->store('payment-slips', 'local'),
                'status' => 'Pending',
            ]);

            $installment->update(['status' => 'Under Review']);

            if ($locked->status === 'Pending Payment') {
                $locked->update(['status' => 'Payment Under Review']);
            }

            Lead::query()
                ->whereKey($locked->lead_id)
                ->where('status', 'Enrolled - Pending Payment')
                ->update(['status' => 'Payment Under Review', 'last_action_at' => now()]);

            return $payment;
        });
    }
}
