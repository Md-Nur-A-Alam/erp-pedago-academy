@extends('layouts.app', ['title' => 'Leave'])

@section('page-title', 'Leave')
@section('page-description', count($chain) === 0 ? 'Apply for leave. Administrators need no approval, so it is granted at once.' : 'Apply for leave under the Bangladesh Labour Act 2006. Approval: '.strtolower(implode(', then ', $chain)).'.')

@section('content')
    @php
        $field = 'h-10 w-full rounded-md border border-pedago-line bg-white px-2.5 text-sm';
        $kinds = [
            'Casual' => 'Casual leave',
            'Sick' => 'Sick leave',
            'Annual' => 'Annual leave',
            'Maternity' => 'Maternity leave',
        ];
        $infos = [
            'Casual' => 'Paid leave for urgent personal matters. Section 115 of the Labour Act 2006 gives 10 days a calendar year.',
            'Sick' => 'Paid leave when you are ill. Section 116 gives 14 days a calendar year. Longer than '.$certificateDays.' days needs a registered doctor’s certificate.',
            'Annual' => 'Also called earned leave. You earn 1 day for every 18 days worked (section 117) and can use it after one full year of service. The balance counts across years.',
            'Maternity' => 'Paid leave of 16 weeks for women employees: 8 before and 8 after the birth (section 46). It needs 6 months of service and counts calendar days.',
        ];
    @endphp

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Leave balance">
        @foreach ($kinds as $key => $label)
            @php $b = $balances[$key]; @endphp
            <x-ui.stat :label="$label" :value="$b['remaining']" :alert="$b['remaining'] === 0" :hint="'of '.$b['entitled'].' · '.$b['used'].' taken · '.$b['pending'].' waiting'" :info="$infos[$key].' Weekly holiday (Friday) is not counted.'" />
        @endforeach
    </section>

    <x-ui.card title="Who approves your leave">
        @if (count($chain) === 0)
            <p class="text-sm leading-6">As an administrator you need no approval. Your leave is <strong>approved automatically</strong> the moment you apply.</p>
        @else
            <ol class="flex flex-wrap items-center gap-2 text-sm">
                @foreach ($chain as $i => $step)
                    <li class="flex items-center gap-2">
                        <span class="num inline-flex size-6 items-center justify-center rounded-full bg-pedago-green-tint text-xs font-semibold text-pedago-green">{{ $i + 1 }}</span>
                        <span class="font-medium">{{ $step }}</span>
                        @unless ($loop->last)<span class="text-pedago-muted" aria-hidden="true">→</span>@endunless
                    </li>
                @endforeach
            </ol>
            <p class="mt-2 text-xs leading-5 text-pedago-muted">
                @if (count($chain) === 2)
                    Your team leader decides first, then a supervisor or administrator gives the final decision. You are on leave only after the final approval.
                @else
                    One decision from {{ strtolower($chain[0]) }} settles it. You are on leave only after it is approved.
                @endif
            </p>
        @endif
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] lg:items-start">
        <x-ui.card title="Apply for leave">
            <form class="flex flex-col gap-4" method="POST" action="{{ route('leave.store') }}">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-semibold" for="leave-type">Type</label>
                    <select class="{{ $field }}" id="leave-type" name="type" required>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('type', 'Casual') === $type)>{{ $type }} — {{ $balances[$type]['remaining'] === null ? 'no pay' : $balances[$type]['remaining'].' days left' }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="leave-start">First day</label>
                        <input class="{{ $field }}" id="leave-start" name="start_date" type="date" min="{{ today()->toDateString() }}" value="{{ old('start_date') }}" required>
                        @error('start_date')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="leave-end">Last day</label>
                        <input class="{{ $field }}" id="leave-end" name="end_date" type="date" min="{{ today()->toDateString() }}" value="{{ old('end_date') }}" required>
                        @error('end_date')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold" for="leave-reason">Reason</label>
                    <textarea class="w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="leave-reason" name="reason" rows="3" maxlength="500" required>{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input class="mt-0.5 size-4 rounded border-pedago-line" type="checkbox" name="has_certificate" value="1" @checked(old('has_certificate'))>
                    <span>I hold a registered doctor's certificate <span class="text-xs text-pedago-muted">(needed for sick leave over {{ $certificateDays }} days)</span></span>
                </label>
                @error('has_certificate')<p class="-mt-2 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                <p class="text-xs leading-5 text-pedago-muted">Friday, the weekly holiday, is not counted (maternity leave counts every day). A request covers up to {{ $maxDays }} days, or 112 for maternity leave. While your approved leave covers today you receive no new leads.</p>
                <x-ui.button type="submit" icon="calendar">Send request</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="My requests" flush>
            <x-slot:meta>Latest {{ $requests->count() }}</x-slot:meta>
            @if ($requests->isEmpty())
                <x-ui.empty class="border-t border-pedago-line" title="You have not applied for leave yet" icon="calendar" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] border-collapse text-left">
                        <thead>
                            <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Dates</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Type</th>
                                <th class="px-2 py-2 text-right font-semibold" scope="col">Days</th>
                                <th class="px-2 py-2 font-semibold" scope="col">Status</th>
                                <th class="px-4 py-2 sm:pr-5" scope="col"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px]">
                            @foreach ($requests as $leave)
                                <tr class="border-b border-pedago-line align-top last:border-0 hover:bg-pedago-row">
                                    <th class="px-4 py-2 font-medium sm:pl-5" scope="row">
                                        <span class="num block text-xs">{{ $leave->start_date->format('M j') }}{{ $leave->days > 1 ? ' – '.$leave->end_date->format('M j, Y') : ', '.$leave->start_date->format('Y') }}</span>
                                        <span class="block max-w-60 truncate text-xs font-normal text-pedago-muted" title="{{ $leave->reason }}">{{ $leave->reason }}</span>
                                    </th>
                                    <td class="px-2 py-2">{{ $leave->type }}</td>
                                    <td class="num px-2 py-2 text-right text-xs">{{ $leave->days }}</td>
                                    <td class="px-2 py-2">
                                        <x-ui.pill :tone="['Pending' => 'amber', 'Approved' => 'green', 'Rejected' => 'red', 'Cancelled' => 'slate'][$leave->status]">{{ $leave->stageLabel() }}</x-ui.pill>
                                        @if ($leave->firstReviewedBy)
                                            <span class="mt-1 block text-xs text-pedago-muted">1st: {{ $leave->firstReviewedBy->name }} approved</span>
                                        @endif
                                        @if ($leave->reviewedBy)
                                            <span class="mt-1 block text-xs text-pedago-muted">{{ $leave->reviewedBy->name }}{{ $leave->review_note ? ': '.$leave->review_note : '' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right sm:pr-5">
                                        @if (in_array($leave->status, ['Pending', 'Approved'], true) && ! ($leave->end_date->isPast() && ! $leave->end_date->isToday()))
                                            <form method="POST" action="{{ route('leave.cancel', $leave) }}" data-confirm="Cancel this leave request?">
                                                @csrf
                                                <x-ui.button type="submit" variant="secondary" size="sm">Cancel</x-ui.button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
