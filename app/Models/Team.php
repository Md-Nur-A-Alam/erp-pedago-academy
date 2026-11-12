<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'team_leader_id', 'is_active', 'edited_by'])]
class Team extends Model
{
    use HasFactory;

    public const UPDATED_AT = 'edited_at';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'team_leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Employee::class, 'team_id');
    }

    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'supervisor_teams', 'team_id', 'supervisor_id');
    }

    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(SupervisorTeam::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'edited_by');
    }
}
