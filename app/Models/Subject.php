<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    public function topics(): HasMany
    {
        return $this->hasMany(CourseTopic::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function campaigns(): HasManyThrough
    {
        return $this->hasManyThrough(LeadSource::class, Course::class)
            ->whereNotNull('leads_source.campaign_name');
    }
}
