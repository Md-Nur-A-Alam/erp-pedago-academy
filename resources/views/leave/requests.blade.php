@extends('layouts.app', ['title' => 'Leave requests'])

@section('page-title', 'Leave requests')
@section('page-description', 'Leave that you decide for the people you manage.')

@section('content')
    @if ($onLeaveToday->isNotEmpty())
        <x-ui.card title="On leave today">
            <ul class="flex flex-wrap gap-2">
                @foreach ($onLeaveToday as $person)
                    <li><x-ui.pill tone="slate">{{ $person->name }}</x-ui.pill></li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.card flush>
        <div class="px-4 pt-1 sm:px-5">
            <x-ui.tabs :tabs="$statuses" :current="$status" :counts="$counts" route="leave.requests" param="status" label="Leave status" />
        </div>

        @if ($requests->isEmpty())
            <x-ui.empty :title="$status === 'Pending' ? 'No leave is waiting for your decision' : 'No '.strtolower($status).' leave requests'" icon="calendar">
                Requests from the people you manage appear here.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-pedago-line">
                @foreach ($requests as $leave)
                    <li class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto] lg:items-start">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $leave->employee?->name }}</p>
                            <p class="text-xs text-pedago-muted">{{ $leave->employee?->role === 'Team Leader' ? 'Team leader' : $leave->employee?->role }} · {{ $leave->employee?->effectiveTeam()?->name ?? 'No team' }}</p>
                            <p class="num mt-2 text-[13px] font-medium">{{ $leave->start_date->format('M j') }}{{ $leave->days > 1 ? ' – '.$leave->end_date->format('M j, Y') : ', '.$leave->start_date->format('Y') }} · {{ $leave->days }} {{ Str::plural('day', $leave->days) }}</p>
                            <p class="text-xs text-pedago-muted">{{ $leave->type }} leave</p>
                            @if ($leave->required_approvals >= 2)
                                <p class="mt-1 text-xs font-medium text-pedago-blue">Approval {{ min($leave->approvals_done + 1, 2) }} of 2</p>
                            @endif
                        </div>
                        <div class="min-w-0 text-[13px]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-pedago-muted">Reason</p>
                            <p class="mt-0.5 break-words leading-5">{{ $leave->reason }}</p>
                            @if ($leave->firstReviewedBy)
                                <p class="mt-2 text-xs text-pedago-muted"><span class="font-semibold text-pedago-ink">1st approval, {{ $leave->firstReviewedBy->name }}.</span> {{ $leave->first_review_note }}</p>
                            @endif
                            @if ($leave->type === 'Sick' && $leave->has_certificate)
                                <p class="mt-2 text-xs text-pedago-muted">Doctor's certificate confirmed.</p>
                            @endif
                            @if ($leave->review_note)
                                <p class="mt-2 text-xs text-pedago-muted"><span class="font-semibold text-pedago-ink">{{ $leave->reviewedBy?->name }}:</span> {{ $leave->review_note }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2 lg:flex-col lg:items-end">
                            @if ($leave->status === 'Pending')
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('leave.decide', $leave) }}" data-confirm="Approve {{ $leave->employee?->name }}'s leave? They receive no new leads on these days.">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="decision" value="approve">
                                        <x-ui.button type="submit" size="sm">{{ $leave->required_approvals >= 2 && $leave->approvals_done === 0 && auth()->user()->employee?->role === 'Team Leader' ? 'Approve (1st)' : 'Approve' }}</x-ui.button>
                                    </form>
                                    <x-ui.button variant="danger" size="sm" data-dialog-open="reject-leave-dialog" data-dialog-action="{{ route('leave.decide', $leave) }}" data-dialog-subject="{{ $leave->employee?->name }} · {{ $leave->start_date->format('M j') }} – {{ $leave->end_date->format('M j') }}">Reject</x-ui.button>
                                </div>
                            @else
                                <x-ui.pill :tone="['Approved' => 'green', 'Rejected' => 'red', 'Cancelled' => 'slate'][$leave->status] ?? 'slate'">{{ $leave->status }}</x-ui.pill>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($requests->hasPages())
                <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $requests->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    <x-ui.dialog id="reject-leave-dialog" title="Reject leave">
        <form class="flex flex-col gap-4" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="decision" value="reject">
            <div>
                <label class="mb-1 block text-sm font-semibold" for="leave-review-note">Reason for rejecting <span class="text-pedago-red" aria-hidden="true">*</span></label>
                <textarea class="w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="leave-review-note" name="review_note" rows="3" maxlength="500" required></textarea>
                <p class="mt-1 text-xs text-pedago-muted">The applicant sees this note.</p>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" data-dialog-close>Cancel</x-ui.button>
                <x-ui.button type="submit" variant="danger">Reject leave</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endsection
