@php
    $isLeader = $employee->role === 'Team Leader';
    $rate = fn (int $part, int $whole): string => $whole > 0 ? number_format($part / $whole * 100, 1).'%' : '—';
@endphp

@if ($teams->isEmpty())
    <x-ui.card>
        <x-ui.empty title="{{ $isLeader ? 'You do not lead a team yet' : 'No team is assigned to you yet' }}" icon="users">
            An administrator assigns teams. Your team’s figures appear here once that is done.
        </x-ui.empty>
    </x-ui.card>
@else
    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" aria-label="Team summary">
        <x-ui.stat label="Executives" :value="number_format($metrics['members'])" :hint="$metrics['available'].' available now'" />
        <x-ui.stat label="Active batches" :value="number_format($metrics['active_batches'])" hint="Being worked now" />
        <x-ui.stat label="Overdue follow-ups" :value="number_format($metrics['overdue'])" :href="route('follow-ups.index')" :alert="$metrics['overdue'] > 0"
            :hint="$metrics['lazy'].' '.Str::plural('executive', $metrics['lazy']).' flagged Lazy'" />
        <x-ui.stat label="Open leads" :value="number_format($metrics['open_leads'])" :href="route('leads.index')" hint="Still in progress" />
        <x-ui.stat label="Converted this month" :value="number_format($metrics['converted_month'])"
            :hint="$rate($metrics['converted_month'], $metrics['assigned_month']).' of '.$metrics['assigned_month'].' assigned'" />
        @if ($isLeader)
            <x-ui.stat label="Transfers to decide" :value="number_format($metrics['transfers_pending'])" :href="route('lead-transfers.index')" hint="Involving your team" />
        @else
            <x-ui.stat label="Teams" :value="number_format($teams->count())" :href="route('teams.index')" hint="Under your supervision" />
        @endif
    </section>

    <x-ui.card :title="$isLeader ? 'My team' : 'My teams'" flush>
        <x-slot:meta>Leads assigned since {{ now()->startOfMonth()->format('M j') }}</x-slot:meta>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[620px] border-collapse text-left">
                <thead>
                    <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                        <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Team</th>
                        <th class="px-2 py-2 font-semibold" scope="col">Leader</th>
                        <th class="px-2 py-2 text-right font-semibold" scope="col">Executives</th>
                        <th class="px-2 py-2 text-right font-semibold" scope="col">Open leads</th>
                        <th class="px-2 py-2 text-right font-semibold" scope="col">Assigned</th>
                        <th class="px-2 py-2 text-right font-semibold" scope="col">Converted</th>
                        <th class="px-4 py-2 text-right font-semibold sm:pr-5" scope="col">Rate</th>
                    </tr>
                </thead>
                <tbody class="text-[13px]">
                    @foreach ($teams as $team)
                        <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                            <th class="px-4 py-2 font-medium sm:pl-5" scope="row"><a class="text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('teams.show', $team) }}">{{ $team->name }}</a></th>
                            <td class="px-2 py-2">{{ $team->teamLeader?->name ?? '—' }}</td>
                            <td class="num px-2 py-2 text-right text-xs">{{ number_format($team->members_count) }}</td>
                            <td class="num px-2 py-2 text-right text-xs">{{ number_format($team->open_count) }}</td>
                            <td class="num px-2 py-2 text-right text-xs">{{ number_format($team->assigned_count) }}</td>
                            <td class="num px-2 py-2 text-right text-xs font-semibold">{{ number_format($team->converted_count) }}</td>
                            <td class="num px-4 py-2 text-right text-xs sm:pr-5">{{ $rate($team->converted_count, $team->assigned_count) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.card title="Assigned but not yet called" flush @class(['border-pedago-red-line' => $uncalled->isNotEmpty()])>
        <x-slot:meta>Longest wait first — a lead cools with every hour it waits</x-slot:meta>
        @if ($uncalled->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="Every assigned lead has had its first call" />
        @else
            <ul class="divide-y divide-pedago-line border-t border-pedago-line">
                @foreach ($uncalled as $lead)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold">{{ $lead->student_name }} <span class="font-normal text-pedago-muted">· with {{ $lead->currentOwner?->name }}</span></p>
                            <p class="text-xs text-pedago-muted">Assigned {{ $lead->assigned_at?->diffForHumans() ?? 'earlier' }} · received {{ $lead->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.pill :status="$lead->temperature()" />
                            <x-ui.button :href="route('leads.workspace', $lead)" variant="secondary" size="sm">View</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card title="Executives — today" flush>
        <x-slot:meta>{{ $roster->count() }} {{ Str::plural('executive', $roster->count()) }} · those who need attention first</x-slot:meta>
        @if ($roster->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No sales executives in {{ $isLeader ? 'your team' : 'these teams' }}" icon="users" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-left">
                    <thead>
                        <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Executive</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Team</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Shift</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Status</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Batch</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Overdue</th>
                            <th class="px-4 py-2 sm:pr-5" scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($roster as $row)
                            <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                <th class="whitespace-nowrap px-4 py-2 font-medium sm:pl-5" scope="row">{{ $row['employee']->name }}</th>
                                <td class="whitespace-nowrap px-2 py-2">{{ $row['team'] ?? '—' }}</td>
                                <td class="num whitespace-nowrap px-2 py-2 text-xs">{{ $row['shift'] ?? 'Day off' }}</td>
                                <td class="px-2 py-2"><x-ui.pill :status="$row['status']" /></td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    @if ($row['batch_total'] > 0)
                                        <span class="mr-1.5 inline-block h-1.5 w-14 overflow-hidden rounded-full bg-pedago-line align-middle" aria-hidden="true"><span class="block h-full bg-pedago-blue" style="width: {{ (int) round($row['batch_done'] / $row['batch_total'] * 100) }}%"></span></span><span class="num text-xs">{{ $row['batch_done'] }}/{{ $row['batch_total'] }}</span>
                                    @else
                                        <span class="text-pedago-muted">—</span>
                                    @endif
                                </td>
                                <td @class(['num px-2 py-2 text-xs', 'font-semibold text-pedago-red' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                                <td class="px-4 py-2 text-right sm:pr-5">
                                    <x-ui.button :href="route('follow-ups.index', ['employee_id' => $row['employee']->id])" variant="secondary" size="sm" aria-label="Follow-ups of {{ $row['employee']->name }}">Follow-ups</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
@endif
