<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public const ROLES = ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'Accounts', 'MarketingExecutive'];

    private const AUTHORITY_LEVELS = [
        'Admin' => 4,
        'Supervisor' => 3,
        'Team Leader' => 2,
        'Sales Executive' => 1,
        'Accounts' => 1,
        'MarketingExecutive' => 1,
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

    public function todayRoster(): HasOne
    {
        return $this->hasOne(EmployeeRoster::class)->whereDate('roster_date', today());
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
