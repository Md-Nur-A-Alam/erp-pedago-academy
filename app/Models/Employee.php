<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'user_id',
    'office_id',
    'name',
    'designation',
    'joining_date',
    'role',
    'status',
    'shift_id',
    'team_id',
    'edited_by',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public const UPDATED_AT = 'edited_at';

    public const ROLES = ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'Accounts', 'MarketingExecutive', 'Developer'];

    private const AUTHORITY_LEVELS = [
        'Admin' => 4,
        'Supervisor' => 3,
        'Team Leader' => 2,
        'Sales Executive' => 1,
        'Accounts' => 1,
        'MarketingExecutive' => 1,
        'Developer' => 0,
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            if (blank($employee->office_id)) {
                $employee->office_id = static::nextOfficeId();
            }
        });

        static::updating(function (Employee $employee): void {
            $wasActiveAdmin = $employee->getOriginal('role') === 'Admin' && $employee->getOriginal('status') === 'Active';
            $staysActiveAdmin = $employee->role === 'Admin' && $employee->status === 'Active';

            if ($wasActiveAdmin && ! $staysActiveAdmin
                && ! static::query()->where('role', 'Admin')->where('status', 'Active')->whereKeyNot($employee->id)->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'The system must keep at least one active administrator. Make someone else an administrator first.',
                ]);
            }
        });
    }

    public static function nextOfficeId(): string
    {
        $officeIds = static::query()
            ->where('office_id', 'like', 'PED-%')
            ->pluck('office_id');

        $maxNumber = 0;
        foreach ($officeIds as $id) {
            if (preg_match('/^PED-(\d+)$/i', (string) $id, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $next = $maxNumber + 1;

        while (static::query()->where('office_id', sprintf('PED-%03d', $next))->exists()) {
            $next++;
        }

        return sprintf('PED-%03d', $next);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasOne
    {
        return $this->hasOne(EmployeeDetails::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(EmployeeChangeRequest::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    /**
     * Team ids this employee works with: supervised teams, led teams, or their own team.
     *
     * @return list<int>
     */
    public function managedTeamIds(): array
    {
        $ids = match ($this->role) {
            'Supervisor' => $this->supervisedTeams()->pluck('teams.id')->all(),
            'Team Leader' => $this->teamsLed()->pluck('id')->push($this->team_id)->all(),
            default => [$this->team_id],
        };

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    /**
     * Sales executives whose work this employee oversees, or null when everyone is in reach.
     *
     * @return list<int>|null
     */
    public function managedExecutiveIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        if ($this->role === 'Sales Executive') {
            return [$this->id];
        }

        if (! in_array($this->role, ['Supervisor', 'Team Leader'], true)) {
            return [];
        }

        return static::query()
            ->where('role', 'Sales Executive')
            ->whereIn('team_id', $this->managedTeamIds())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Leads this employee is allowed to see.
     *
     * @return Builder<Lead>
     */
    public function visibleLeads(): Builder
    {
        $query = Lead::query();

        if ($this->isAdmin() || $this->role === 'MarketingExecutive') {
            return $query;
        }

        if ($this->role === 'Sales Executive') {
            return $query->where('current_owner_id', $this->id);
        }

        if (in_array($this->role, ['Supervisor', 'Team Leader'], true)) {
            return $query->where(fn (Builder $scope) => $scope
                ->whereIn('team_id', $this->managedTeamIds())
                ->orWhereIn('current_owner_id', $this->managedExecutiveIds()));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isProfileComplete(): bool
    {
        $details = $this->details;

        return $details !== null && $details->isComplete();
    }

    public function canBeReviewedBy(self $approver): bool
    {
        return $approver->id !== $this->id
            && (self::AUTHORITY_LEVELS[$approver->role] ?? 0) > (self::AUTHORITY_LEVELS[$this->role] ?? 0);
    }

    /**
     * @return list<string>
     */
    public function lowerAuthorityRoles(): array
    {
        $authorityLevel = self::AUTHORITY_LEVELS[$this->role] ?? 0;

        return collect(self::AUTHORITY_LEVELS)
            ->filter(fn (int $level): bool => $level < $authorityLevel)
            ->keys()
            ->values()
            ->all();
    }

    public function defaultShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(EmployeeRoster::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'edited_by');
    }

    public function editedEmployees(): HasMany
    {
        return $this->hasMany(self::class, 'edited_by');
    }

    public function teamsLed(): HasMany
    {
        return $this->hasMany(Team::class, 'team_leader_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function effectiveTeam(): ?Team
    {
        if ($this->team !== null) {
            return $this->team;
        }

        if ($this->relationLoaded('teamsLed')) {
            return $this->teamsLed->first();
        }

        return $this->teamsLed()->first();
    }

    public function todayRoster(): HasOne
    {
        return $this->hasOne(EmployeeRoster::class)->whereDate('roster_date', today());
    }

    public function effectiveShift(?string $date = null): ?Shift
    {
        $targetDate = $date ?? today()->toDateString();

        $roster = $this->relationLoaded('rosters')
            ? $this->rosters->first(fn (EmployeeRoster $r): bool => $r->roster_date->toDateString() === $targetDate)
            : ($targetDate === today()->toDateString() && $this->relationLoaded('todayRoster')
                ? $this->todayRoster
                : $this->rosters()->whereDate('roster_date', $targetDate)->with('shift')->first());

        if ($roster !== null) {
            if (! $roster->is_on_duty) {
                return null;
            }
            if ($roster->shift !== null) {
                return $roster->shift;
            }
        }

        $effectiveTeam = $this->effectiveTeam();
        if ($effectiveTeam !== null && $effectiveTeam->shift !== null) {
            return $effectiveTeam->shift;
        }

        return $this->defaultShift;
    }

    public function effectiveAvailabilityStatus(?string $date = null, ?Carbon $now = null): string
    {
        $targetDate = $date ?? today()->toDateString();
        $isToday = $targetDate === today()->toDateString();
        $currentTime = $now ?? Carbon::now();

        // 1. Employee core status
        if ($this->status === 'Inactive') {
            return 'Unavailable';
        }
        if ($this->status === 'Suspended') {
            return 'Unavailable';
        }

        // 2. Check date-specific roster override
        $roster = $this->relationLoaded('rosters')
            ? $this->rosters->first(fn (EmployeeRoster $r): bool => $r->roster_date->toDateString() === $targetDate)
            : ($isToday && $this->relationLoaded('todayRoster')
                ? $this->todayRoster
                : $this->rosters()->whereDate('roster_date', $targetDate)->first());

        if ($roster !== null) {
            // Day Off / Holiday
            if (! $roster->is_on_duty) {
                return $roster->availability_status ?? 'Holiday';
            }

            // Explicit manual states set by Admin
            if (in_array($roster->availability_status, ['Unavailable', 'Busy', 'Lazy', 'Holiday', 'Resigned', 'Cut Off'], true)) {
                return $roster->availability_status;
            }
        }

        // 3. Resolve effective shift (Override -> Team -> Individual default)
        $shift = $this->effectiveShift($targetDate);
        if ($shift === null) {
            return ($roster !== null && ! $roster->is_on_duty) ? 'Holiday' : 'Off Duty';
        }

        // 4. For today, evaluate against wall-clock shift window & cutoff
        if ($isToday) {
            $shiftStart = Carbon::parse($currentTime->toDateString().' '.$shift->shift_start_time);
            $shiftEnd = Carbon::parse($currentTime->toDateString().' '.$shift->shift_end_time);
            if ($shiftEnd->lessThan($shiftStart)) {
                $shiftEnd->addDay();
            }

            $cutoffMinutes = SystemSetting::integer('assignment_cutoff_minutes');
            $cutoffTime = (clone $shiftEnd)->subMinutes($cutoffMinutes);

            // Outside shift working window
            if ($currentTime->lessThan($shiftStart)) {
                return 'Off Duty';
            }
            if ($currentTime->greaterThanOrEqualTo($shiftEnd)) {
                return 'Off Duty';
            }

            // Inside cutoff window (last X minutes before shift ends)
            if ($currentTime->greaterThanOrEqualTo($cutoffTime)) {
                return 'Cut Off';
            }

            // Check if Lazy: follow-ups overdue beyond the configured threshold without action
            $lazyThresholdHours = SystemSetting::integer('lazy_threshold_hours');
            $hasLazyOverdue = $this->relationLoaded('leadFollowups')
                ? $this->leadFollowups->contains(fn (LeadFollowup $f): bool => ! $f->is_done && $f->followup_date_time && $f->followup_date_time->lt($currentTime->copy()->subHours($lazyThresholdHours)))
                : $this->leadFollowups()->where('is_done', false)->where('followup_date_time', '<', $currentTime->copy()->subHours($lazyThresholdHours))->exists();

            if ($hasLazyOverdue) {
                return 'Lazy';
            }

            // Check if Busy: currently has an active incomplete batch
            $hasActiveBatch = $this->relationLoaded('batches')
                ? $this->batches->contains(function (LeadBatch $b): bool {
                    return $b->status === 'Active' && $b->items->contains(fn ($item): bool => ! $item->is_completed);
                })
                : $this->batches()->where('status', 'Active')->whereHas('items', fn ($q) => $q->where('is_completed', false))->exists();

            if ($hasActiveBatch) {
                return 'Busy';
            }
        }

        return 'Available';
    }

    public function supervisedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'supervisor_teams', 'supervisor_id', 'team_id');
    }

    public function ownedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'current_owner_id');
    }

    public function previouslyOwnedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'previous_owner_id');
    }

    public function leadsAssigned(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_by_employee_id');
    }

    public function leadsEdited(): HasMany
    {
        return $this->hasMany(Lead::class, 'edited_by');
    }

    public function coursesEdited(): HasMany
    {
        return $this->hasMany(Course::class, 'edited_by');
    }

    public function leadSourcesEdited(): HasMany
    {
        return $this->hasMany(LeadSource::class, 'edited_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(LeadBatch::class);
    }

    public function batchesAssigned(): HasMany
    {
        return $this->hasMany(LeadBatch::class, 'assigned_by_employee_id');
    }

    public function leadFeedbacks(): HasMany
    {
        return $this->hasMany(LeadFeedback::class);
    }

    public function leadFollowups(): HasMany
    {
        return $this->hasMany(LeadFollowup::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(LeadTransfer::class, 'from_employee_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(LeadTransfer::class, 'to_employee_id');
    }

    public function senderApprovedTransfers(): HasMany
    {
        return $this->hasMany(LeadTransfer::class, 'sender_tl_employee_id');
    }

    public function receiverApprovedTransfers(): HasMany
    {
        return $this->hasMany(LeadTransfer::class, 'receiver_tl_employee_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'sales_executive_id');
    }

    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'verified_by_employee_id');
    }

    public function duplicateLeadReviews(): HasMany
    {
        return $this->hasMany(DuplicateLeadReview::class, 'reviewed_by_employee_id');
    }
}
