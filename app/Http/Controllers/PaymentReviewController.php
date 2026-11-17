<?php

namespace App\Http\Controllers;

use App\Actions\ReviewPayment;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentReviewController extends Controller
{
    private const STATUSES = ['Pending', 'Verified', 'Rejected'];

    private const ROLES = ['Admin', 'Accounts'];

    public function index(Request $request): View
    {
        $this->employeeWithRole($request, self::ROLES);
        $filters = $request->validate(['status' => ['nullable', Rule::in(self::STATUSES)]]);
        $status = $filters['status'] ?? 'Pending';

        $payments = Payment::query()
            ->where('status', $status)
            ->with([
                'enrollment:id,lead_id,course_id,sales_executive_id,student_name,student_phone,final_deal_amount,total_installments,status',
                'enrollment.course:id,title',
                'enrollment.salesExecutive:id,name',
                'installmentSchedule:id,installment_number,due_amount,due_date',
                'verifiedBy:id,name',
            ])
            ->orderBy($status === 'Pending' ? 'created_at' : 'verified_date', $status === 'Pending' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', [
            'statuses' => self::STATUSES,
            'status' => $status,
            'payments' => $payments,
            'counts' => Payment::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'pendingAmount' => (float) Payment::query()->where('status', 'Pending')->sum('amount'),
            'verifiedToday' => (float) Payment::query()
                ->where('status', 'Verified')
                ->whereBetween('verified_date', [now()->startOfDay(), now()->endOfDay()])
                ->sum('amount'),
            'oldestPending' => Payment::query()->where('status', 'Pending')->oldest()->first(),
        ]);
    }

    public function update(Request $request, Payment $payment, ReviewPayment $review): RedirectResponse
    {
        $reviewer = $this->employeeWithRole($request, self::ROLES);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['verify', 'reject'])],
            'rejection_reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:500'],
        ], [
            'rejection_reason.required_if' => 'Give the reason for rejecting this payment.',
        ]);

        if ($validated['decision'] === 'verify') {
            $review->verify($payment, $reviewer);

            return back()->with('status', 'Payment of ৳'.number_format((float) $payment->amount).' verified. The student is enrolled and the lead is converted.');
        }

        $review->reject($payment, $reviewer, $validated['rejection_reason']);

        return back()->with('status', 'Payment of ৳'.number_format((float) $payment->amount).' rejected.');
    }
}
