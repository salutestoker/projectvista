<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TimelineTask extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'timeline_template_id',
        'title',
        'phase',
        'description',
        'sort_order',
        'status',
        'starts_on',
        'due_on',
        'completed_on',
        'client_visible',
        'subcontractor_visible',
        'requires_acknowledgement',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'due_on' => 'date',
            'completed_on' => 'date',
            'client_visible' => 'boolean',
            'subcontractor_visible' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TimelineTemplate::class, 'timeline_template_id');
    }
}
