<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'employee_id',
    'type',
    'start_date',
    'end_date',
    'days',
    'reason',
    'has_certificate',
    'status',
    'required_approvals',
    'approvals_done',
    'first_reviewed_by_employee_id',
    'first_review_note',
    'first_reviewed_at',
    'reviewed_by_employee_id',
    'review_note',
    'reviewed_at',
])]
class LeaveRequest extends Model
{
    use HasFactory;

    /**
     * Kinds of leave a person can apply for, from the Bangladesh Labour Act 2006.
     */
    public const TYPES = ['Casual', 'Sick', 'Annual', 'Maternity', 'Unpaid'];

    /**
     * Longest single request, in days. Maternity leave is longer by law.
     */
    public const MAX_DAYS = 30;

    public const MAX_MATERNITY_DAYS = 112;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'has_certificate' => 'boolean',
            'required_approvals' => 'integer',
            'approvals_done' => 'integer',
            'first_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by_employee_id');
    }

    public function firstReviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'first_reviewed_by_employee_id');
    }

    /**
     * Days that count against the balance. Friday is the weekly holiday, so it is not counted, except in
     * maternity leave, which runs on calendar days.
     */
    public static function countDays(string $start, string $end, string $type = 'Casual'): int
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        $days = 0;

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if ($type === 'Maternity' || ! $day->isFriday()) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * Requests whose dates touch the given range.
     *
     * @param  Builder<LeaveRequest>  $query
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): void
    {
        $query->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start);
    }

    /**
     * How many approvals a request needs. Administrators need none. A sales executive needs two when the
     * team has a leader (the leader, then a supervisor or administrator). Everyone else needs one decision
     * from someone with higher authority.
     */
    public static function approvalsRequired(Employee $applicant): int
    {
        if ($applicant->isAdmin()) {
            return 0;
        }

        if ($applicant->role === 'Sales Executive' && $applicant->team?->team_leader_id !== null) {
            return 2;
        }

        return 1;
    }

    /**
     * The people who decide, in order, as short labels for the applicant to read.
     *
     * @return list<string>
     */
    public static function approvalChain(Employee $applicant): array
    {
        return match (self::approvalsRequired($applicant)) {
            0 => [],
            2 => ['Team leader', 'Supervisor or administrator'],
            default => [$applicant->role === 'Supervisor' ? 'Administrator' : 'Supervisor or administrator'],
        };
    }

    /**
     * Where a pending request stands, in words.
     */
    public function stageLabel(): string
    {
        if ($this->status !== 'Pending') {
            return $this->status;
        }

        if ($this->required_approvals >= 2) {
            return $this->approvals_done === 0 ? 'Waiting for team leader' : 'Waiting for supervisor or admin';
        }

        return $this->employee?->role === 'Supervisor' ? 'Waiting for admin' : 'Waiting for supervisor or admin';
    }

    /**
     * Whether the reviewer holds the authority to decide this request at its current step. Administrators
     * can decide any step. Nobody decides their own request.
     */
    public function canBeReviewedBy(Employee $reviewer): bool
    {
        $applicant = $this->employee;

        if ($applicant === null || $reviewer->id === $applicant->id) {
            return false;
        }

        if ($reviewer->isAdmin()) {
            return true;
        }

        $sharesTeam = array_intersect($applicant->managedTeamIds(), $reviewer->managedTeamIds()) !== [];

        return match ($applicant->role) {
            'Sales Executive' => $this->required_approvals >= 2 && $this->approvals_done === 0
                ? $reviewer->role === 'Team Leader' && $applicant->team?->team_leader_id === $reviewer->id
                : $reviewer->role === 'Supervisor' && $sharesTeam,
            'Team Leader' => $reviewer->role === 'Supervisor' && $sharesTeam,
            'Supervisor' => false,
            default => $reviewer->role === 'Supervisor',
        };
    }

    /**
     * Every request the reviewer may see, whatever its status.
     *
     * @return Builder<LeaveRequest>
     */
    public static function reviewableBy(Employee $reviewer): Builder
    {
        $query = static::query()->where('employee_id', '!=', $reviewer->id);

        if ($reviewer->isAdmin()) {
            return $query;
        }

        $teamIds = $reviewer->managedTeamIds();

        return match ($reviewer->role) {
            'Team Leader' => $query->whereHas('employee', fn (Builder $applicant) => $applicant
                ->where('role', 'Sales Executive')->whereIn('team_id', $teamIds)),
            'Supervisor' => $query->whereHas('employee', fn (Builder $applicant) => $applicant
                ->where(fn (Builder $scope) => $scope
                    ->where(fn (Builder $executive) => $executive->where('role', 'Sales Executive')->whereIn('team_id', $teamIds))
                    ->orWhere(fn (Builder $leader) => $leader->where('role', 'Team Leader')
                        ->whereIn('id', Team::query()->whereIn('id', $teamIds)->select('team_leader_id')))
                    ->orWhereIn('role', ['Accounts', 'MarketingExecutive']))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Pending requests waiting for this reviewer's decision now, not for someone earlier in the chain.
     *
     * @return Builder<LeaveRequest>
     */
    public static function awaiting(Employee $reviewer): Builder
    {
        $query = static::reviewableBy($reviewer)->where('status', 'Pending');

        return match (true) {
            $reviewer->isAdmin() => $query,
            $reviewer->role === 'Team Leader' => $query->where('required_approvals', '>=', 2)->where('approvals_done', 0),
            default => $query->where(fn (Builder $step) => $step->where('required_approvals', '<', 2)->orWhere('approvals_done', '>=', 1)),
        };
    }
}
