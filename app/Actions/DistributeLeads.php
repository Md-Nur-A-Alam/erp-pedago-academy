<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadEvent;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DistributeLeads
{
    /**
     * Unassigned new leads, newest first. The engine only takes leads whose campaign is in Auto mode.
     *
     * @return Builder<Lead>
     */
    public function queueQuery(bool $autoOnly = false): Builder
    {
        return Lead::query()
            ->where('status', 'New')
            ->whereNull('current_owner_id')
            ->when($autoOnly, fn (Builder $query) => $query->whereHas(
                'source',
                fn (Builder $source) => $source->where('distribution_mode', 'Auto')->where('is_active', true),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Choose the leads for one cycle by speed-to-lead priority.
     *
     * Fresh leads (hot, then warm) go first, newest first, with high-intent channels ahead inside each
     * temperature. One slot in four is reserved for the longest-waiting stale lead so that nothing is
     * left in the queue forever.
     *
     * @return Collection<int, Lead>
     */
    public function pickForCycle(int $count): Collection
    {
        if ($count <= 0) {
            return new Collection;
        }

        $warmCutoff = now()->subHours(SystemSetting::integer('warm_lead_hours'));
        $fresh = $this->queueQuery(autoOnly: true)->with('source:source_id,channel')->where('created_at', '>=', $warmCutoff)->limit($count)->get();
        $staleTotal = $this->queueQuery(autoOnly: true)->where('created_at', '<', $warmCutoff)->count();

        $reserved = $fresh->isNotEmpty() ? min($staleTotal, intdiv($count, 4)) : 0;
        $oldest = $reserved > 0
            ? $this->queueQuery(autoOnly: true)->with('source:source_id,channel')->where('created_at', '<', $warmCutoff)
                ->reorder('created_at')->orderBy('id')->limit($reserved)->get()
            : new Collection;

        $picked = $fresh->take($count - $reserved)->concat($oldest);
        $missing = $count - $picked->count();

        if ($missing > 0) {
            $picked = $picked->concat(
                $this->queueQuery(autoOnly: true)->with('source:source_id,channel')
                    ->whereNotIn('id', $picked->pluck('id'))
                    ->limit($missing)
                    ->get(),
            );
        }

        return (new Collection($picked->all()))
            ->sortBy(fn (Lead $lead): array => [$lead->priorityRank(), -$lead->created_at->getTimestamp()])
            ->values();
    }

    /**
     * Sales people (executives and team leaders) who can receive a batch right now, longest idle first.
     *
     * @param  Collection<int, Employee>|null  $executives  Executives already loaded by executivesWithAvailability()
     * @return Collection<int, Employee>
     */
    public function availableExecutives(?Collection $executives = null): Collection
    {
        return ($executives ?? $this->executivesWithAvailability())
            ->filter(fn (Employee $executive): bool => $executive->availability === 'Available' && $executive->receivesAutoLeads() && $executive->remainingDailyCap() !== 0)
            ->sort(function (Employee $first, Employee $second): int {
                return [$first->last_batch_completed_at !== null, (string) $first->last_batch_completed_at, $first->id]
                    <=> [$second->last_batch_completed_at !== null, (string) $second->last_batch_completed_at, $second->id];
            })
            ->values();
    }

    /**
     * Every active sales executive with today's computed availability in an `availability` attribute.
     *
     * @return Collection<int, Employee>
     */
    public function executivesWithAvailability(): Collection
    {
        return Employee::query()
            ->whereIn('role', Employee::SALES_ROLES)
            ->where('status', 'Active')
            ->with([
                'todayLeave',
                'team.shift',
                'teamsLed.shift',
                'defaultShift',
                'todayRoster.shift',
                'leadFollowups' => fn ($followups) => $followups->where('is_done', false),
                'batches' => fn ($batches) => $batches->where('status', 'Active')->with('items'),
            ])
            ->withMax('batches as last_batch_completed_at', 'completed_at')
            ->orderBy('name')
            ->get()
            ->each(function (Employee $executive): void {
                $executive->setAttribute('availability', $executive->effectiveAvailabilityStatus());
            });
    }

    /**
     * Work out the next cycle without changing anything.
     *
     * @return array{queue: int, executives: int, batch_size: int, base: int, extra: int, rule: string, note: string, assignable: int, remaining: int, allocations: list<array{employee: Employee, count: int, base: int, extra: int}>}
     */
    public function plan(?Collection $executives = null): array
    {
        $executives ??= $this->availableExecutives();
        $batchSize = max(1, SystemSetting::integer('lead_batch_size'));
        $queue = $this->queueQuery(autoOnly: true)->count();
        $executiveCount = $executives->count();

        $base = $executiveCount > 0 ? min($batchSize, intdiv($queue, $executiveCount)) : 0;
        $extra = ($executiveCount > 0 && $base < $batchSize) ? $queue % $executiveCount : 0;

        $allocations = [];
        foreach ($executives as $index => $executive) {
            $bonus = $index < $extra ? 1 : 0;
            $count = min($base + $bonus, $executive->remainingDailyCap() ?? PHP_INT_MAX);
            if ($count > 0) {
                $allocations[] = ['employee' => $executive, 'count' => $count, 'base' => min($base, $count), 'extra' => max(0, $count - $base)];
            }
        }
        $assignable = array_sum(array_column($allocations, 'count'));

        return [
            'queue' => $queue,
            'executives' => $executiveCount,
            'batch_size' => $batchSize,
            'base' => $base,
            'extra' => $extra,
            'rule' => $this->ruleLabel($queue, $executiveCount, $batchSize, $base),
            'note' => $this->ruleNote($queue, $executiveCount, $batchSize, $base, $extra),
            'assignable' => $assignable,
            'remaining' => $queue - $assignable,
            'allocations' => $allocations,
        ];
    }

    /**
     * Run one distribution cycle.
     *
     * @return array{batches: int, leads: int}
     */
    public function run(?Employee $triggeredBy = null): array
    {
        return DB::transaction(function () use ($triggeredBy): array {
            $plan = $this->plan();
            if ($plan['assignable'] === 0) {
                return ['batches' => 0, 'leads' => 0];
            }

            $picked = $this->pickForCycle($plan['assignable']);
            $order = $picked->pluck('id')->flip();
            $leads = $this->queueQuery(autoOnly: true)
                ->whereIn('id', $picked->pluck('id'))
                ->lockForUpdate()
                ->get()
                ->sortBy(fn (Lead $lead): int => $order[$lead->id])
                ->values();

            /** @var array<int, list<Lead>> $hands */
            $hands = [];
            $rounds = max(array_column($plan['allocations'], 'count'));
            for ($round = 0; $round < $rounds; $round++) {
                foreach ($plan['allocations'] as $index => $allocation) {
                    if ($allocation['count'] > $round && $leads->isNotEmpty()) {
                        $hands[$index][] = $leads->shift();
                    }
                }
            }

            $assigned = 0;
            foreach ($hands as $index => $hand) {
                $this->assignBatch($plan['allocations'][$index]['employee'], new Collection($hand), $triggeredBy);
                $plan['allocations'][$index]['employee']->addAutoLeads(count($hand));
                $assigned += count($hand);
            }

            return ['batches' => count($hands), 'leads' => $assigned];
        }, 3);
    }

    /**
     * Give leads to one executive as a batch, recording the previous owner. Leads are added to the
     * executive's open batch when one exists.
     *
     * @param  Collection<int, Lead>  $leads
     */
    public function assignBatch(Employee $executive, Collection $leads, ?Employee $assignedBy): LeadBatch
    {
        return DB::transaction(function () use ($executive, $leads, $assignedBy): LeadBatch {
            $batch = LeadBatch::query()
                ->where('employee_id', $executive->id)
                ->where('status', 'Active')
                ->lockForUpdate()
                ->first();

            if ($batch === null) {
                $batch = LeadBatch::query()->create([
                    'employee_id' => $executive->id,
                    'assigned_by_employee_id' => $assignedBy?->id,
                    'batch_size' => 0,
                    'status' => 'Active',
                    'assigned_at' => now(),
                ]);
            }

            $teamId = $executive->effectiveTeam()?->id;

            foreach ($leads as $lead) {
                if ($lead->current_owner_id !== null && $lead->current_owner_id !== $executive->id) {
                    $lead->previous_owner_id = $lead->current_owner_id;
                }
                $lead->current_owner_id = $executive->id;
                $lead->team_id = $teamId;
                $lead->assigned_by_employee_id = $assignedBy?->id;
                $lead->assigned_at = now();
                $lead->edited_by = $assignedBy?->id;
                if ($lead->status === 'New') {
                    $lead->status = 'Assigned';
                }
                $lead->save();

                $batch->items()->firstOrCreate(['lead_id' => $lead->id], ['is_completed' => false]);
                LeadEvent::record($lead, $assignedBy, 'Assigned', "Assigned to {$executive->name}".($assignedBy === null ? ' by the engine' : " by {$assignedBy->name}").'.');
            }

            $batch->update(['batch_size' => $batch->items()->count()]);

            $count = $leads->count();
            app(Notifier::class)->send($executive, 'leads.assigned', $count === 1 ? '1 new lead for you' : "{$count} new leads for you", 'Open My tasks and call them in order. Hot leads first.', route('sales.tasks'), 'green');

            return $batch;
        });
    }

    private function ruleLabel(int $queue, int $executives, int $batchSize, int $base): string
    {
        if ($executives === 0) {
            return 'No one available';
        }
        if ($queue === 0) {
            return 'Queue empty';
        }
        if ($base >= $batchSize) {
            return "L ≥ {$batchSize}S";
        }
        if ($base === 0) {
            return 'L < S';
        }

        return $base === 1 ? 'S ≤ L < 2S' : "{$base}S ≤ L < ".($base + 1).'S';
    }

    private function ruleNote(int $queue, int $executives, int $batchSize, int $base, int $extra): string
    {
        if ($executives === 0) {
            return 'No sales executive is available right now, so the queue waits. Leads are assigned as soon as someone becomes available.';
        }
        if ($queue === 0) {
            return 'There are no auto-distributed leads in the queue. Available executives receive a batch when new leads arrive.';
        }
        if ($base >= $batchSize) {
            $remaining = $queue - ($batchSize * $executives);

            return "Enough leads for full batches: {$batchSize} each".($remaining > 0 ? ", and {$remaining} stay in the queue for the next cycle." : '.');
        }
        if ($base === 0) {
            return "Fewer leads than executives: one lead each to the {$extra} longest-idle executives (outlined).";
        }

        return "Scarcity: {$base} each (solid)".($extra > 0 ? ", then the remaining {$extra} go round-robin to the longest-idle executives (outlined)." : '.');
    }
}
