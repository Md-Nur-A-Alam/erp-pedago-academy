<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['channel', 'campaign_name', 'course_id', 'distribution_mode', 'is_active', 'created_by', 'edited_by'])]
class LeadSource extends Model
{
    use HasFactory;

    public const CHANNELS = [
        'fb' => 'Facebook',
        'wp' => 'WhatsApp',
        'googleads' => 'Google Ads',
        'gptads' => 'GPT Ads',
        'googleform' => 'Google Form',
        'reference' => 'Reference',
        'website' => 'Website',
        'phone' => 'Phone',
        'event' => 'Event',
        'manual' => 'Manual',
        'other' => 'Other',
    ];

    public const DISTRIBUTION_MODES = ['Auto', 'Manual'];

    protected $table = 'leads_source';

    protected $primaryKey = 'source_id';

    public const UPDATED_AT = 'edited_at';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'source_id', 'source_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'edited_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
