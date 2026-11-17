@extends('layouts.app', ['title' => 'Lead L-'.$lead->id])

@section('page-title', $lead->student_name)
@section('page-description', 'Lead L-'.$lead->id.' · '.($lead->currentOwner?->name ? 'owned by '.$lead->currentOwner->name : 'no owner'))

@section('page-actions')
    <x-ui.pill :status="$lead->status" />
@endsection

@section('content')
    @php
        $field = 'h-10 w-full rounded-md border border-pedago-line bg-white px-2.5 text-sm';
        $details = [
            'Phone' => $lead->contact_number,
            'WhatsApp' => $lead->whatsapp_number,
            'Other phone' => $lead->alternative_contact_number,
            'Guardian' => $lead->guardian_name,
            'Email' => $lead->email,
            'Location' => $lead->location,
            'Class' => $lead->class,
            'Age' => $lead->age,
            'Contact is' => $lead->student_parent_type,
            'Course' => $lead->interestedCourse?->title,
            'Campaign' => $lead->source?->campaign_name,
            'Channel' => \App\Models\LeadSource::CHANNELS[$lead->source?->channel ?? ''] ?? $lead->source?->channel,
            'Priority' => $lead->priority,
            'Remarks' => $lead->remarks,
            'Team' => $lead->team?->name,
            'Received' => $lead->created_at?->format('M j, Y'),
        ];
    @endphp

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <div class="flex flex-col gap-4">
            <x-ui.card title="Lead details">
                <a class="num mb-3 inline-flex min-h-11 items-center gap-2 rounded-md border border-pedago-blue bg-pedago-tint px-3 text-base font-semibold text-pedago-blue-dark transition-colors duration-150 hover:bg-white" href="tel:{{ $lead->contact_number }}">Call {{ $lead->contact_number }}</a>
                <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-[13px]">
                    @foreach ($details as $term => $value)
                        @continue(blank($value))
                        <dt class="text-pedago-muted">{{ $term }}</dt>
                        <dd class="break-words font-medium">{{ $value }}</dd>
                    @endforeach
                </dl>
                @if (filled($lead->student_background))
                    <p class="mt-3 rounded-md bg-pedago-canvas px-3 py-2 text-[13px] leading-5">{{ $lead->student_background }}</p>
                @endif

                <div class="mt-4 border-t border-pedago-line pt-3">
                    <h3 class="text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Other numbers of this lead</h3>
                    <ul class="mt-1 divide-y divide-pedago-line">
                        @forelse ($lead->phones as $phone)
                            <li class="flex items-center justify-between gap-3 py-1.5 text-[13px]">
                                <span><span class="num font-medium">{{ $phone->phone_number }}</span> @if ($phone->label)<span class="text-xs text-pedago-muted">{{ $phone->label }}</span>@endif</span>
                                @if ($canEditPhones)
                                    <form method="POST" action="{{ route('lead-phones.destroy', $phone) }}" data-confirm="Remove {{ $phone->phone_number }} from this lead?">@csrf @method('DELETE')<button class="text-xs font-semibold text-pedago-red hover:underline" type="submit">Remove</button></form>
                                @endif
                            </li>
                        @empty
                            <li class="py-1.5 text-[13px] text-pedago-muted">None. Add a parent's or sibling's number so a call from it finds this lead.</li>
                        @endforelse
                    </ul>
                    @if ($canEditPhones)
                        <form class="mt-2 flex flex-wrap items-start gap-2" method="POST" action="{{ route('lead-phones.store', $lead) }}">
                            @csrf
                            <div class="min-w-0 flex-1">
                                <input class="{{ $field }}" name="phone_number" type="tel" placeholder="01XXXXXXXXX" value="{{ old('phone_number') }}" aria-label="Another number for this lead" required>
                                @error('phone_number')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                            </div>
                            <input class="{{ $field }} sm:w-36" name="label" type="text" maxlength="50" placeholder="Whose? (optional)" value="{{ old('label') }}" aria-label="Whose number">
                            <x-ui.button type="submit" variant="secondary" size="sm">Add number</x-ui.button>
                        </form>
                    @endif
                </div>

                @if ($canWork)
                    <form class="mt-3" method="POST" action="{{ route('leads.whatsapp', $lead) }}" target="_blank">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">Thank on WhatsApp</x-ui.button>
                        <span class="ml-1 text-xs text-pedago-muted">Opens WhatsApp with the thank-you message ready to send.</span>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="History">
                <x-slot:meta>{{ $lead->feedbacks->count() }} {{ Str::plural('call', $lead->feedbacks->count()) }} logged</x-slot:meta>
                <ol class="flex flex-col gap-3">
                    @forelse ($lead->feedbacks as $entry)
                        <li class="border-l-2 border-pedago-line pl-3">
                            <p class="text-xs text-pedago-muted"><span class="num">{{ $entry->created_at?->format('M j, g:i A') }}</span> · {{ $entry->employee?->name }}</p>
                            <p class="mt-0.5 text-[13px] font-semibold">{{ $entry->call_status }} · interest {{ strtolower($entry->customer_interest) }} · next: {{ $entry->next_action }}</p>
                            <p class="mt-0.5 break-words text-[13px] leading-5">{{ $entry->sales_note }}</p>
                            @if (filled($entry->customer_concern))
                                <p class="mt-0.5 break-words text-xs text-pedago-muted">Concern: {{ $entry->customer_concern }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-[13px] text-pedago-muted">No call has been logged for this lead yet.</li>
                    @endforelse
                </ol>
                @php
                    $activity = $lead->events->map(fn ($event) => ['at' => $event->created_at, 'who' => $event->employee?->name, 'text' => $event->note])
                        ->concat($lead->inquiries->map(fn ($inquiry) => ['at' => $inquiry->created_at, 'who' => null, 'text' => 'Inquired again'.($inquiry->source?->campaign_name ? ' via '.$inquiry->source->campaign_name : '').($inquiry->outcome ? '. '.$inquiry->outcome : '.')]))
                        ->sortByDesc('at')->values();
                @endphp
                @if ($activity->isNotEmpty())
                    <h3 class="mt-4 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Other activity</h3>
                    <ul class="mt-1 divide-y divide-pedago-line">
                        @foreach ($activity as $item)
                            <li class="py-1.5 text-[13px]"><span class="num text-xs text-pedago-muted">{{ $item['at']?->format('M j, g:i A') }}</span> <span class="break-words">{{ $item['text'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($lead->followups->isNotEmpty())
                    <h3 class="mt-4 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Follow-ups</h3>
                    <ul class="mt-1 divide-y divide-pedago-line">
                        @foreach ($lead->followups as $followUp)
                            <li class="flex items-center justify-between gap-3 py-1.5 text-[13px]">
                                <span class="num text-xs">{{ $followUp->followup_date_time->format('M j, Y g:i A') }}</span>
                                <x-ui.pill :tone="$followUp->is_done ? 'slate' : ($followUp->followup_date_time->isPast() ? 'red' : 'blue')">{{ $followUp->is_done ? 'Closed' : ($followUp->followup_date_time->isPast() ? 'Overdue' : 'Scheduled') }}</x-ui.pill>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-4">
            @if ($enrollment)
                @php
                    $taka = fn ($amount) => 'Tk '.number_format((float) $amount, 2);
                    $payable = $enrollment->installmentSchedules->whereIn('status', ['Pending', 'Overdue']);
                    $tones = ['Pending' => 'amber', 'Under Review' => 'blue', 'Paid' => 'green', 'Overdue' => 'red', 'Verified' => 'green', 'Rejected' => 'red'];
                @endphp
                <x-ui.card title="Enrollment and payment">
                    <x-slot:meta><x-ui.pill :tone="['Enrolled' => 'green', 'Cancelled' => 'slate'][$enrollment->status] ?? 'amber'">{{ $enrollment->status }}</x-ui.pill></x-slot:meta>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[13px]">
                        <div><dt class="text-xs text-pedago-muted">Course</dt><dd class="font-medium">{{ $enrollment->course?->title }}</dd></div>
                        <div><dt class="text-xs text-pedago-muted">Batch</dt><dd class="font-medium">{{ $enrollment->courseBatch?->batch_name ?? 'Batch '.$enrollment->courseBatch?->batch_number }}</dd></div>
                        <div><dt class="text-xs text-pedago-muted">Course fee</dt><dd class="num font-medium">{{ $taka($enrollment->base_course_fee) }}</dd></div>
                        <div><dt class="text-xs text-pedago-muted">Discount</dt><dd class="num font-medium">{{ rtrim(rtrim(number_format((float) $enrollment->discount_percentage, 2), '0'), '.') }}%</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-pedago-muted">Student pays</dt><dd class="num text-base font-bold">{{ $taka($enrollment->final_deal_amount) }}</dd></div>
                    </dl>

                    <h3 class="mt-4 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Installments</h3>
                    <ul class="mt-1 divide-y divide-pedago-line">
                        @foreach ($enrollment->installmentSchedules as $installment)
                            @php $late = $installment->status === 'Pending' && $installment->due_date->isPast() && ! $installment->due_date->isToday(); @endphp
                            <li class="flex items-center justify-between gap-3 py-1.5 text-[13px]">
                                <span><span class="num font-medium">#{{ $installment->installment_number }}</span> <span class="num text-xs text-pedago-muted">due {{ $installment->due_date->format('M j, Y') }}</span></span>
                                <span class="flex items-center gap-2"><span class="num">{{ $taka($installment->due_amount) }}</span><x-ui.pill :tone="$tones[$late ? 'Overdue' : $installment->status] ?? 'slate'">{{ $late ? 'Overdue' : $installment->status }}</x-ui.pill></span>
                            </li>
                        @endforeach
                    </ul>

                    @if ($enrollment->payments->isNotEmpty())
                        <h3 class="mt-4 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted">Payments sent to Accounts</h3>
                        <ul class="mt-1 divide-y divide-pedago-line">
                            @foreach ($enrollment->payments as $payment)
                                <li class="py-1.5 text-[13px]">
                                    <div class="flex items-center justify-between gap-3">
                                        <span>{{ $payment->payment_method }} <span class="num text-xs text-pedago-muted">{{ $payment->transaction_id ? 'TrxID '.$payment->transaction_id : '' }}</span></span>
                                        <span class="flex items-center gap-2"><span class="num">{{ $taka($payment->amount) }}</span><x-ui.pill :tone="$tones[$payment->status] ?? 'slate'">{{ $payment->status }}</x-ui.pill></span>
                                    </div>
                                    @if ($payment->slip_screenshot_path)<a class="text-xs font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('payments.slip', $payment) }}" target="_blank" rel="noopener">View slip</a>@endif
                                    @if ($payment->status === 'Rejected' && $payment->rejection_reason)<p class="text-xs font-medium text-pedago-red">Rejected: {{ $payment->rejection_reason }}</p>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($canPay && $payable->isNotEmpty())
                        <form class="mt-4 flex flex-col gap-3 border-t border-pedago-line pt-4" method="POST" action="{{ route('enrollments.payments.store', $enrollment) }}" enctype="multipart/form-data" data-payment-form>
                            @csrf
                            <h3 class="text-sm font-semibold">Submit a payment</h3>
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="installment_schedule_id">Installment</label>
                                <select class="{{ $field }}" id="installment_schedule_id" name="installment_schedule_id" required data-installment>
                                    @foreach ($payable as $installment)
                                        <option value="{{ $installment->id }}" data-amount="{{ (float) $installment->due_amount }}" @selected((string) old('installment_schedule_id') === (string) $installment->id)>#{{ $installment->installment_number }} · {{ $taka($installment->due_amount) }} · due {{ $installment->due_date->format('M j') }}</option>
                                    @endforeach
                                </select>
                                @error('installment_schedule_id')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold" for="amount">Amount received</label>
                                    <input class="{{ $field }}" id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', (float) $payable->first()->due_amount) }}" required data-amount>
                                    @error('amount')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold" for="payment_method">Paid by</label>
                                    <select class="{{ $field }}" id="payment_method" name="payment_method" required>
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold" for="transaction_id">Transaction ID</label>
                                    <input class="{{ $field }}" id="transaction_id" name="transaction_id" type="text" maxlength="100" value="{{ old('transaction_id') }}">
                                    @error('transaction_id')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold" for="payment_date">Paid on</label>
                                    <input class="{{ $field }}" id="payment_date" name="payment_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('payment_date', today()->toDateString()) }}">
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="slip">Slip or screenshot <span class="font-normal text-pedago-muted">(JPG, PNG or PDF, up to 4 MB)</span></label>
                                <input class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-pedago-line file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-semibold" id="slip" name="slip" type="file" accept=".jpg,.jpeg,.png,.pdf">
                                @error('slip')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                            </div>
                            <p class="text-xs text-pedago-muted">Give the transaction ID, the slip, or both. Accounts check it against the bank or wallet record. The lead converts when they verify it.</p>
                            <x-ui.button type="submit" icon="credit-card">Send to Accounts</x-ui.button>
                        </form>
                        <script>
                            document.querySelector('[data-payment-form] [data-installment]')?.addEventListener('change', (event) => {
                                const amount = event.target.selectedOptions[0]?.dataset.amount;
                                if (amount) event.target.closest('form').querySelector('[data-amount]').value = amount;
                            });
                        </script>
                    @endif
                </x-ui.card>
            @endif

            @if (! $canWork)
                <x-ui.card>
                    <p class="text-sm text-pedago-muted">You can read this lead’s history. Only its owner records calls and requests transfers.</p>
                </x-ui.card>
            @elseif ($isClosed)
                <x-ui.card>
                    <p class="text-sm text-pedago-muted">This lead is in enrollment ({{ $lead->status }}), so call feedback is closed.</p>
                </x-ui.card>
            @else
                @if ($canEnroll)
                    <x-ui.card title="Convert this lead">
                        <p class="text-sm leading-6 text-pedago-muted">The student agreed to join? Enroll them: pick the course, give any discount and set the installments. Then collect the first payment.</p>
                        <x-ui.button class="mt-3" :href="route('enrollments.create', $lead)" icon="check-square">Enroll this student</x-ui.button>
                    </x-ui.card>
                @endif

                <x-ui.card title="Call feedback">
                    <form class="flex flex-col gap-4" method="POST" action="{{ route('leads.feedback', $lead) }}">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="call_status">Call result <span class="text-pedago-red" aria-hidden="true">*</span></label>
                                <select class="{{ $field }}" id="call_status" name="call_status" required>
                                    @foreach ($callStatuses as $option)
                                        <option value="{{ $option }}" @selected(old('call_status') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="customer_interest">Interest <span class="text-pedago-red" aria-hidden="true">*</span></label>
                                <select class="{{ $field }}" id="customer_interest" name="customer_interest" required>
                                    @foreach ($interests as $option)
                                        <option value="{{ $option }}" @selected(old('customer_interest', 'Medium') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold" for="sales_note">Note about the call <span class="text-pedago-red" aria-hidden="true">*</span></label>
                            <textarea class="w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="sales_note" name="sales_note" rows="3" maxlength="2000" required>{{ old('sales_note') }}</textarea>
                            @error('sales_note')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold" for="customer_concern">Customer’s concern <span class="font-normal text-pedago-muted">(optional)</span></label>
                            <input class="{{ $field }}" id="customer_concern" name="customer_concern" type="text" maxlength="1000" value="{{ old('customer_concern') }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold" for="priority">Priority of this lead <span class="font-normal text-pedago-muted">(important leads come first on their day)</span></label>
                            <select class="{{ $field }}" id="priority" name="priority">
                                <option value="">Keep as is{{ $lead->priority ? ' ('.$lead->priority.')' : '' }}</option>
                                @foreach ($priorities as $option)
                                    <option value="{{ $option }}" @selected(old('priority') === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <details class="rounded-md border border-pedago-line px-3 py-2" @if ($errors->hasAny(['student_name', 'guardian_name', 'remarks'])) open @endif>
                            <summary class="cursor-pointer text-sm font-semibold">Correct the lead's details <span class="font-normal text-pedago-muted">(name, guardian, standing remark)</span></summary>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold" for="student_name">Student name</label>
                                    <input class="{{ $field }}" id="student_name" name="student_name" type="text" maxlength="150" value="{{ old('student_name', $lead->student_name) }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold" for="guardian_name">Guardian name</label>
                                    <input class="{{ $field }}" id="guardian_name" name="guardian_name" type="text" maxlength="150" value="{{ old('guardian_name', $lead->guardian_name) }}">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-sm font-semibold" for="remarks">Remark about this lead <span class="font-normal text-pedago-muted">(stays on the lead, unlike the call note)</span></label>
                                    <textarea class="w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="remarks" name="remarks" rows="2" maxlength="1000">{{ old('remarks', $lead->remarks) }}</textarea>
                                </div>
                            </div>
                        </details>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="next_action">What happens next <span class="text-pedago-red" aria-hidden="true">*</span></label>
                                <select class="{{ $field }}" id="next_action" name="next_action" required>
                                    @foreach ($nextActions as $option)
                                        <option value="{{ $option }}" @selected(old('next_action', 'Follow-up') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="followup_date_time">Call back on</label>
                                <input class="{{ $field }}" id="followup_date_time" name="followup_date_time" type="datetime-local" value="{{ old('followup_date_time') }}" min="{{ now()->format('Y-m-d\TH:i') }}" aria-describedby="followup-help">
                                <p class="mt-1 text-xs text-pedago-muted" id="followup-help">Required for Follow-up, and for Interested when the call connected.</p>
                                @error('followup_date_time')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <x-ui.button type="submit">Save feedback</x-ui.button>
                        </div>
                    </form>
                    <script>
                        (() => {
                            const call = document.getElementById('call_status');
                            const next = document.getElementById('next_action');
                            if (!call || !next) return;
                            call.addEventListener('change', () => {
                                if (call.value === 'Wrong Number') next.value = 'Invalid/Junk';
                            });
                        })();
                    </script>
                </x-ui.card>

                <x-ui.card title="Transfer this lead">
                    @if ($pendingTransfer)
                        <p class="text-sm text-pedago-muted">A transfer to <span class="font-semibold text-pedago-ink">{{ $pendingTransfer->toEmployee?->name }}</span> ({{ $pendingTransfer->toTeam?->name }}) is waiting for approval since {{ $pendingTransfer->created_at?->format('M j') }}.</p>
                    @elseif ($colleagues->isEmpty())
                        <p class="text-sm text-pedago-muted">No other sales executive in a team is available to receive this lead.</p>
                    @else
                        <form class="flex flex-col gap-3" method="POST" action="{{ route('leads.transfer-request', $lead) }}">
                            @csrf
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="to_employee_id">Send to</label>
                                <select class="{{ $field }}" id="to_employee_id" name="to_employee_id" required>
                                    <option value="">Choose a sales executive</option>
                                    @foreach ($colleagues as $colleague)
                                        <option value="{{ $colleague->id }}" @selected((string) old('to_employee_id') === (string) $colleague->id)>{{ $colleague->name }} — {{ $colleague->team?->name }}</option>
                                    @endforeach
                                </select>
                                @error('to_employee_id')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold" for="transfer-reason">Reason</label>
                                <textarea class="w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="transfer-reason" name="reason" rows="2" maxlength="500" required>{{ old('reason') }}</textarea>
                                @error('reason')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-pedago-muted">The lead stays with you until the transfer is approved.</p>
                                <x-ui.button type="submit" variant="secondary" icon="repeat">Request transfer</x-ui.button>
                            </div>
                        </form>
                    @endif
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
