@extends('layouts.app', ['title' => 'Follow-ups'])

@section('page-title', $personal ? 'My follow-ups' : ($viewer->role === 'Team Leader' ? 'Team follow-ups' : 'Follow-ups'))
@section('page-description', $personal ? 'Call-backs you promised to your leads.' : 'Scheduled call-backs across the sales team you oversee.')

@section('content')
    @if ($lazyExecutives->isNotEmpty())
        <x-ui.card title="Executives flagged Lazy" class="border-pedago-red-line">
            <x-slot:meta>A follow-up overdue for more than {{ $lazyThreshold }} {{ Str::plural('hour', $lazyThreshold) }}</x-slot:meta>
            <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($lazyExecutives as $executive)
                    <li>
                        <a class="flex items-center justify-between gap-3 rounded-md border border-pedago-line px-3 py-2 transition-colors duration-150 hover:border-pedago-blue hover:bg-pedago-row" href="{{ route('follow-ups.index', ['employee_id' => $executive->id]) }}">
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-semibold">{{ $executive->name }}</span>
                                <span class="block truncate text-xs text-pedago-muted">{{ $executive->team?->name ?? 'No team' }}</span>
                            </span>
                            <span class="flex shrink-0 flex-col items-end gap-1"><x-ui.pill tone="red">{{ $executive->overdue_count }} overdue</x-ui.pill>@if ($executive->coming_count > 0)<x-ui.pill tone="blue">{{ $executive->coming_count }} coming up</x-ui.pill>@endif</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.card flush>
        @if (! $personal)
        <div class="flex flex-wrap items-end justify-between gap-3 px-4 pt-3 sm:px-5">
            <form class="flex flex-wrap items-end gap-2" method="GET" action="{{ route('follow-ups.index') }}" aria-label="Filter follow-ups">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="w-64 max-w-full">
                    <label class="mb-1 block text-xs font-medium text-pedago-muted" for="follow-up-executive">Sales executive</label>
                    <select class="h-9 w-full rounded-md border border-pedago-line bg-white px-2 text-sm" id="follow-up-executive" name="employee_id">
                        <option value="">Everyone</option>
                        @foreach ($executives as $executive)
                            <option value="{{ $executive->id }}" @selected($employeeId === $executive->id)>{{ $executive->name }}{{ $executive->overdue_count > 0 ? ' — '.$executive->overdue_count.' overdue' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
                @if ($employeeId !== null)
                    <a class="inline-flex min-h-9 items-center px-2 text-sm font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route($personal ? 'follow-ups.mine' : 'follow-ups.index', ['tab' => $tab]) }}">Clear</a>
                @endif
            </form>
        </div>
        @endif

        <div class="mt-2 px-4 sm:px-5">
            <p class="px-1 pb-2 pt-3 text-xs leading-5 text-pedago-muted"><span class="font-semibold text-pedago-blue-dark">Coming up</span>: from {{ $noticeHours }} {{ Str::plural('hour', $noticeHours) }} before the time until the exact time, with a reminder. <span class="font-semibold text-pedago-red">Overdue</span>: from the exact time until a new follow-up, a conversion or another outcome is logged.</p>
            <x-ui.tabs :tabs="$tabs" :current="$tab" :counts="$counts" route="follow-ups.index" :query="array_filter(['employee_id' => $employeeId])" label="Follow-up views" />
        </div>

        @if ($followUps->isEmpty())
            @php $emptyTitle = ['overdue' => 'No overdue follow-ups', 'soon' => 'Nothing is coming up soon', 'later' => 'Nothing is scheduled further ahead', 'done' => 'No completed follow-ups yet'][$tab]; @endphp
            <x-ui.empty :title="$emptyTitle" icon="clock">
                {{ $tab === 'overdue' ? 'Every scheduled call-back has been made on time.' : 'Follow-ups appear here when executives schedule a call-back from a lead.' }}
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Scheduled</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Lead</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Course</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Executive</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Note</th>
                            <th class="px-4 py-2 sm:pr-5" scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($followUps as $followUp)
                            @php $isOverdue = ! $followUp->is_done && $followUp->followup_date_time->isPast(); @endphp
                            <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                <td class="whitespace-nowrap px-4 py-2 sm:pl-5">
                                    <span class="num block text-xs">{{ $followUp->followup_date_time->format('M j, g:i A') }}</span>
                                    @if ($isOverdue)
                                        <span class="block text-xs font-semibold text-pedago-red">Overdue by {{ $followUp->followup_date_time->diffForHumans(null, true) }}</span>
                                    @elseif ($followUp->is_done)
                                        <span class="block text-xs text-pedago-green">Completed</span>
                                    @elseif ($followUp->phase() === 'Coming up')
                                        <span class="block text-xs font-semibold text-pedago-blue-dark">Coming up, in {{ $followUp->followup_date_time->diffForHumans(null, true) }}</span>
                                    @else
                                        <span class="block text-xs text-pedago-muted">In {{ $followUp->followup_date_time->diffForHumans(null, true) }}</span>
                                    @endif
                                </td>
                                <th class="px-2 py-2 font-medium" scope="row">
                                    <span class="block max-w-52 truncate">{{ $followUp->lead?->student_name ?? 'Lead removed' }}</span>
                                    <span class="num block text-xs font-normal text-pedago-muted">L-{{ $followUp->lead_id }} · {{ $followUp->lead?->contact_number }}</span>
                                </th>
                                <td class="px-2 py-2"><span class="block max-w-44 truncate">{{ $followUp->lead?->interestedCourse?->title ?? '—' }}</span></td>
                                <td class="px-2 py-2">
                                    <span class="block max-w-44 truncate">{{ $followUp->employee?->name }}</span>
                                    <span class="block text-xs text-pedago-muted">{{ $followUp->employee?->team?->name ?? 'No team' }}</span>
                                </td>
                                <td class="px-2 py-2"><span class="block max-w-64 truncate text-pedago-muted" title="{{ $followUp->note }}">{{ $followUp->note ?: '—' }}</span></td>
                                <td class="px-4 py-2 text-right sm:pr-5">
                                    @if (($personal || $followUp->employee_id === $viewer->id) && $viewer->isSalesPerson() && ! $followUp->is_done)
                                        <x-ui.button :href="route('leads.workspace', $followUp->lead_id)" size="sm">Call now</x-ui.button>
                                    @elseif ($canReassign && ! $followUp->is_done)
                                        <x-ui.button variant="secondary" size="sm" data-dialog-open="reassign-dialog"
                                            data-dialog-action="{{ route('follow-ups.reassign', $followUp) }}"
                                            data-dialog-subject="L-{{ $followUp->lead_id }} · {{ $followUp->lead?->student_name }} · now with {{ $followUp->employee?->name }}"
                                            aria-label="Reassign follow-up for {{ $followUp->lead?->student_name }}">Reassign</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($followUps->hasPages())
                <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $followUps->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    @if ($canReassign)
    <x-ui.dialog id="reassign-dialog" title="Reassign follow-up">
        <form class="flex flex-col gap-4" method="POST" action="">
            @csrf
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-sm font-semibold">Where should this lead go?</legend>
                <label class="flex items-start gap-2.5 rounded-md border border-pedago-line px-3 py-2.5 text-sm has-[:checked]:border-pedago-blue has-[:checked]:bg-pedago-tint" for="reassign-target-executive">
                    <input class="mt-0.5 size-4 accent-pedago-blue" id="reassign-target-executive" type="radio" name="target" value="executive" checked>
                    <span><span class="block font-semibold">Another sales executive</span><span class="block text-xs text-pedago-muted">The lead and its open follow-ups move to the person you choose.</span></span>
                </label>
                <label class="flex items-start gap-2.5 rounded-md border border-pedago-line px-3 py-2.5 text-sm has-[:checked]:border-pedago-blue has-[:checked]:bg-pedago-tint" for="reassign-target-queue">
                    <input class="mt-0.5 size-4 accent-pedago-blue" id="reassign-target-queue" type="radio" name="target" value="queue">
                    <span><span class="block font-semibold">Back to the central queue</span><span class="block text-xs text-pedago-muted">The lead becomes New and unowned, and its open follow-ups are closed.</span></span>
                </label>
            </fieldset>
            <div>
                <label class="mb-1 block text-sm font-semibold" for="reassign-employee">New owner</label>
                <select class="h-10 w-full rounded-md border border-pedago-line bg-white px-2 text-sm" id="reassign-employee" name="employee_id">
                    <option value="">Choose a sales executive</option>
                    @foreach ($executives as $executive)
                        <option value="{{ $executive->id }}">{{ $executive->name }} ({{ $executive->office_id }}){{ $executive->overdue_count > 0 ? ' — '.$executive->overdue_count.' overdue' : '' }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-pedago-muted">Only needed when the lead goes to another executive.</p>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" data-dialog-close>Cancel</x-ui.button>
                <x-ui.button type="submit">Reassign</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
    @endif
@endsection
