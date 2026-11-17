@extends('layouts.app', ['title' => 'Payments review'])

@section('page-title', 'Payments review')
@section('page-description', 'Check each submitted payment against the bank or wallet statement before you verify it.')

@section('content')
    @php $taka = fn (float $amount): string => '৳ '.number_format($amount); @endphp

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Payment summary">
        <x-ui.stat label="Waiting for review" :value="number_format($counts['Pending'] ?? 0)" :alert="($counts['Pending'] ?? 0) > 0"
            :hint="$oldestPending ? 'Oldest waiting '.$oldestPending->created_at->diffForHumans(null, true) : 'Nothing waiting'" />
        <x-ui.stat label="Amount waiting" :value="$taka($pendingAmount)" hint="Across pending payments" />
        <x-ui.stat label="Verified today" :value="$taka($verifiedToday)" hint="Collected and confirmed" />
        <x-ui.stat label="Rejected" :value="number_format($counts['Rejected'] ?? 0)" hint="All time" />
    </section>

    <x-ui.card flush>
        <div class="px-4 pt-1 sm:px-5">
            <x-ui.tabs :tabs="$statuses" :current="$status" :counts="$counts" route="payments.index" param="status" label="Payment status" />
        </div>

        @if ($payments->isEmpty())
            <x-ui.empty :title="$status === 'Pending' ? 'No payment is waiting for review' : 'No '.strtolower($status).' payments'" icon="credit-card">
                Payments appear here when a student pays online or submits a bank or mobile-wallet slip.
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[940px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Submitted</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Student</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Course</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Installment</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Amount</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Method</th>
                            <th class="px-4 py-2 font-semibold sm:pr-5" scope="col">{{ $status === 'Pending' ? 'Decision' : 'Reviewed' }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($payments as $payment)
                            @php $enrollment = $payment->enrollment; @endphp
                            <tr class="border-b border-pedago-line align-top transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                <td class="whitespace-nowrap px-4 py-2.5 sm:pl-5">
                                    <span class="num block text-xs">{{ ($payment->payment_date ?? $payment->created_at)?->format('M j, g:i A') }}</span>
                                    @if ($status === 'Pending')
                                        <span class="block text-xs text-pedago-muted">Waiting {{ $payment->created_at?->diffForHumans(null, true) }}</span>
                                    @endif
                                </td>
                                <th class="px-2 py-2.5 font-medium" scope="row">
                                    <span class="block max-w-48 truncate">{{ $enrollment?->student_name }}</span>
                                    <span class="num block text-xs font-normal text-pedago-muted">{{ $enrollment?->student_phone }}</span>
                                </th>
                                <td class="px-2 py-2.5">
                                    <span class="block max-w-48 truncate">{{ $enrollment?->course?->title ?? '—' }}</span>
                                    <span class="block text-xs text-pedago-muted">Sold by {{ $enrollment?->salesExecutive?->name ?? '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2.5">
                                    @if ($payment->installmentSchedule)
                                        <span class="num">{{ $payment->installmentSchedule->installment_number }}</span> of <span class="num">{{ $enrollment?->total_installments }}</span>
                                        <span class="block text-xs text-pedago-muted">Due {{ $payment->installmentSchedule->due_date->format('M j') }} · {{ $taka((float) $payment->installmentSchedule->due_amount) }}</span>
                                    @else
                                        <span class="text-pedago-muted">Not linked</span>
                                    @endif
                                </td>
                                <td class="num whitespace-nowrap px-2 py-2.5 text-right font-semibold">{{ $taka((float) $payment->amount) }}</td>
                                <td class="px-2 py-2.5">
                                    <span class="block whitespace-nowrap">{{ $payment->payment_method }}</span>
                                    <span class="num block max-w-40 truncate text-xs text-pedago-muted" title="{{ $payment->transaction_id }}">{{ $payment->transaction_id ? 'TrxID '.$payment->transaction_id : ($payment->slip_screenshot_path ? 'Slip attached' : 'No reference given') }}</span>
                                </td>
                                <td class="px-4 py-2.5 sm:pr-5">
                                    @if ($payment->status === 'Pending')
                                        <div class="flex gap-2">
                                            <form method="POST" action="{{ route('payments.update', $payment) }}" data-confirm="Verify {{ $taka((float) $payment->amount) }} from {{ $enrollment?->student_name }}? The student is enrolled and the lead is marked Converted.">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="decision" value="verify">
                                                <x-ui.button type="submit" size="sm" aria-label="Verify payment from {{ $enrollment?->student_name }}">Verify</x-ui.button>
                                            </form>
                                            <x-ui.button variant="danger" size="sm" data-dialog-open="reject-payment-dialog"
                                                data-dialog-action="{{ route('payments.update', $payment) }}"
                                                data-dialog-subject="{{ $taka((float) $payment->amount) }} · {{ $payment->payment_method }} · {{ $enrollment?->student_name }}"
                                                aria-label="Reject payment from {{ $enrollment?->student_name }}">Reject</x-ui.button>
                                        </div>
                                    @else
                                        <x-ui.pill :status="$payment->status" />
                                        <span class="mt-1 block text-xs text-pedago-muted">{{ $payment->verifiedBy?->name ?? '—' }} · {{ $payment->verified_date?->format('M j, Y') }}</span>
                                        @if ($payment->rejection_reason)
                                            <span class="mt-1 block max-w-64 text-xs leading-5 text-pedago-red">{{ $payment->rejection_reason }}</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $payments->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    <x-ui.dialog id="reject-payment-dialog" title="Reject payment">
        <form class="flex flex-col gap-4" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="decision" value="reject">
            <div>
                <label class="mb-1 block text-sm font-semibold" for="payment-rejection-reason">Reason for rejecting <span class="text-pedago-red" aria-hidden="true">*</span></label>
                <textarea class="w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="payment-rejection-reason" name="rejection_reason" rows="3" maxlength="500" required aria-describedby="payment-rejection-help"></textarea>
                <p class="mt-1 text-xs text-pedago-muted" id="payment-rejection-help">For example: the transaction ID is not in the statement, or the amount does not match. The enrollment goes back to awaiting payment.</p>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" data-dialog-close>Cancel</x-ui.button>
                <x-ui.button type="submit" variant="danger">Reject payment</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endsection
