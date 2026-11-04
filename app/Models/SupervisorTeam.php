<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supervisor_id', 'team_id', 'edited_by'])]
class SupervisorTeam extends Model
{
    use HasFactory;

    public const UPDATED_AT = 'edited_at';

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'edited_by');
    }
}
