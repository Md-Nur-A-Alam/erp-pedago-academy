@extends('layouts.app', ['title' => 'Transfers'])

@section('page-title', 'Transfers')
@section('page-description', ($viewer->isAdmin() ? 'Requests to move a lead between executives. ' : 'Requests that involve your team. Both team leaders must approve. ').'Pending requests are approved automatically after '.$autoApproveDays.' '.Str::plural('day', $autoApproveDays).'.')

@section('content')
    <x-ui.card flush>
        <div class="px-4 pt-1 sm:px-5">
            <x-ui.tabs :tabs="$statuses" :current="$status" :counts="$counts" route="lead-transfers.index" param="status" label="Transfer status" />
        </div>

        @if ($transfers->isEmpty())
            <x-ui.empty :title="$status === 'Pending' ? 'No transfer is waiting for a decision' : 'No '.strtolower($status).' transfers'" icon="repeat">
                Transfer requests appear here when an executive asks to hand a lead to another team.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-pedago-line">
                @foreach ($transfers as $transfer)
                    @php $day = min($autoApproveDays, (int) $transfer->created_at->diffInDays(now()) + 1); @endphp
                    <li class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_auto] lg:items-start">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $transfer->lead?->student_name ?? 'Lead removed' }}</p>
                            <p class="num text-xs text-pedago-muted">L-{{ $transfer->lead_id }} · {{ $transfer->lead?->contact_number }}</p>
                            <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px]">
                                <span><span class="font-semibold">{{ $transfer->fromEmployee?->name }}</span> <span class="text-pedago-muted">({{ $transfer->fromTeam?->name }})</span></span>
                                <x-ui.icon name="chevron-right" class="size-4 text-pedago-muted" /><span class="sr-only">to</span>
                                <span><span class="font-semibold">{{ $transfer->toEmployee?->name }}</span> <span class="text-pedago-muted">({{ $transfer->toTeam?->name }})</span></span>
                            </p>
                        </div>

                        <div class="min-w-0 text-[13px]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-pedago-muted">Reason</p>
                            <p class="mt-0.5 break-words leading-5">{{ $transfer->reason }}</p>
                            <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-pedago-muted">
                                <div class="flex items-center gap-1.5">
                                    <dt>Sending team leader:</dt>
                                    <dd><x-ui.pill :tone="$transfer->sender_tl_approved_at ? 'green' : 'slate'">{{ $transfer->sender_tl_approved_at ? 'Approved' : 'Waiting' }}</x-ui.pill></dd>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <dt>Receiving team leader:</dt>
                                    <dd><x-ui.pill :tone="$transfer->receiver_tl_approved_at ? 'green' : 'slate'">{{ $transfer->receiver_tl_approved_at ? 'Approved' : 'Waiting' }}</x-ui.pill></dd>
                                </div>
                            </dl>
                            @if ($transfer->decision_note)
                                <p class="mt-2 text-xs text-pedago-muted"><span class="font-semibold text-pedago-ink">Decision note:</span> {{ $transfer->decision_note }}</p>
                            @endif
                        </div>

                        <div class="flex flex-row flex-wrap items-center gap-2 lg:flex-col lg:items-end">
                            @if ($transfer->status === 'Pending')
                                <x-ui.pill :tone="$day >= $autoApproveDays ? 'red' : 'amber'">Day {{ $day }} of {{ $autoApproveDays }}</x-ui.pill>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('lead-transfers.update', $transfer) }}" data-confirm="{{ $viewer->isAdmin() ? 'Approve this transfer? The lead and its open follow-ups move to '.$transfer->toEmployee?->name.' now.' : 'Approve this transfer on behalf of your team?' }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="decision" value="approve">
                                        <x-ui.button type="submit" size="sm">Approve</x-ui.button>
                                    </form>
                                    <x-ui.button variant="danger" size="sm" data-dialog-open="reject-transfer-dialog"
                                        data-dialog-action="{{ route('lead-transfers.update', $transfer) }}"
                                        data-dialog-subject="L-{{ $transfer->lead_id }} · {{ $transfer->fromEmployee?->name }} to {{ $transfer->toEmployee?->name }}">Reject</x-ui.button>
                                </div>
                            @else
                                <x-ui.pill :status="$transfer->status" />
                                <p class="text-xs text-pedago-muted lg:text-right">
                                    {{ $transfer->is_auto_transferred ? 'Automatically' : 'By '.($transfer->decidedBy?->name ?? 'team leaders') }}
                                    · {{ ($transfer->transferred_at ?? $transfer->updated_at)?->format('M j, Y') }}
                                </p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($transfers->hasPages())
                <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $transfers->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    <x-ui.dialog id="reject-transfer-dialog" title="Reject transfer">
        <form class="flex flex-col gap-4" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="decision" value="reject">
            <div>
                <label class="mb-1 block text-sm font-semibold" for="transfer-decision-note">Reason for rejecting <span class="text-pedago-red" aria-hidden="true">*</span></label>
                <textarea class="w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="transfer-decision-note" name="decision_note" rows="3" maxlength="500" required aria-describedby="transfer-decision-help"></textarea>
                <p class="mt-1 text-xs text-pedago-muted" id="transfer-decision-help">The note is saved with the decision. The lead stays with its current owner.</p>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" data-dialog-close>Cancel</x-ui.button>
                <x-ui.button type="submit" variant="danger">Reject transfer</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endsection
