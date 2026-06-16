<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TimelineTaskTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'timeline_template_id',
        'default_subcontractor_type_id',
        'name',
        'description',
        'sequence_order',
        'default_duration_working_days',
        'internal_only',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'default_duration_working_days' => 'integer',
            'internal_only' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function timelineTemplate(): BelongsTo
    {
        return $this->belongsTo(TimelineTemplate::class);
    }

    public function defaultSubcontractorType(): BelongsTo
    {
        return $this->belongsTo(SubcontractorType::class, 'default_subcontractor_type_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TimelineTask::class);
    }
}
