@extends('layouts.app', ['title' => 'Assignment controls'])

@section('page-title', 'Assignment controls')
@section('page-description', 'Choose who receives leads automatically, and how many a day.')

@section('content')
    <x-ui.card flush>
        <div class="px-4 py-3 text-sm leading-6 text-pedago-muted sm:px-5">
            <span class="font-semibold text-pedago-ink">Auto</span> people receive leads from the engine.
            <span class="font-semibold text-pedago-ink">Manual</span> people never do: only a person can hand them a lead. The <span class="font-semibold text-pedago-ink">daily limit</span> caps what the engine gives someone in a day (empty means no limit). Leads you assign or reassign by hand, and customers returning to their previous owner, are not counted.
        </div>
        @if ($people->isEmpty())
            <x-ui.empty title="No sales people yet" icon="users">Add sales executives or team leaders under Employees.</x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-left">
                    <thead>
                        <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Person</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Team</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Engine gave today</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Mode</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Daily limit</th>
                            <th class="px-4 py-2 sm:pr-5" scope="col"><span class="sr-only">Save</span></th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($people as $person)
                            @php $formId = 'assign-'.$person->id; @endphp
                            <tr class="border-b border-pedago-line align-middle last:border-0 hover:bg-pedago-row">
                                <th class="px-4 py-2 font-medium sm:pl-5" scope="row">{{ $person->name }}<span class="block text-xs font-normal text-pedago-muted">{{ $person->role === 'Team Leader' ? 'Team leader' : 'Sales executive' }}</span></th>
                                <td class="px-2 py-2">{{ $person->effectiveTeam()?->name ?? '—' }}</td>
                                <td class="num px-2 py-2 text-right">{{ $person->autoLeadsToday() }}@if ($person->daily_lead_cap !== null) <span class="text-xs text-pedago-muted">/ {{ $person->daily_lead_cap }}</span>@endif</td>
                                <td class="px-2 py-2">
                                    <select class="h-9 rounded-md border border-pedago-line bg-white px-2 text-sm" name="assign_mode" form="{{ $formId }}" aria-label="Mode for {{ $person->name }}">
                                        <option value="Auto" @selected($person->assign_mode !== 'Manual')>Auto</option>
                                        <option value="Manual" @selected($person->assign_mode === 'Manual')>Manual</option>
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <input class="num h-9 w-24 rounded-md border border-pedago-line bg-white px-2 text-sm" name="daily_lead_cap" type="number" min="0" max="500" placeholder="No limit" value="{{ $person->daily_lead_cap }}" form="{{ $formId }}" aria-label="Daily limit for {{ $person->name }}">
                                </td>
                                <td class="px-4 py-2 text-right sm:pr-5">
                                    <form id="{{ $formId }}" method="POST" action="{{ route('assignment-controls.update', $person) }}">@csrf @method('PUT')<x-ui.button type="submit" variant="secondary" size="sm">Save</x-ui.button></form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
@endsection
