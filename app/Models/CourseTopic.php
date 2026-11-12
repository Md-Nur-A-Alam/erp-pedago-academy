<?php

namespace App\Models;

use Database\Factories\CourseTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['subject_id', 'name'])]
class CourseTopic extends Model
{
    /** @use HasFactory<CourseTopicFactory> */
    use HasFactory;

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'course_topic_id');
    }

    public function campaigns(): HasManyThrough
    {
        return $this->hasManyThrough(LeadSource::class, Course::class, 'course_topic_id', 'course_id')
            ->whereNotNull('leads_source.campaign_name');
    }
}
