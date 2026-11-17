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
            @if (! $canWork)
                <x-ui.card>
                    <p class="text-sm text-pedago-muted">You can read this lead’s history. Only its owner records calls and requests transfers.</p>
                </x-ui.card>
            @elseif ($isClosed)
                <x-ui.card>
                    <p class="text-sm text-pedago-muted">This lead is in enrollment ({{ $lead->status }}), so call feedback is closed.</p>
                </x-ui.card>
            @else
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
                                <p class="mt-1 text-xs text-pedago-muted" id="followup-help">Required for Follow-up. Optional for Interested.</p>
                                @error('followup_date_time')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <x-ui.button type="submit">Save feedback</x-ui.button>
                        </div>
                    </form>
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
