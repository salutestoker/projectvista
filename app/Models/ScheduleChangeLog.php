<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScheduleChangeLog extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'timeline_task_id',
        'user_id',
        'old_status',
        'new_status',
        'old_start_date',
        'new_start_date',
        'old_end_date',
        'new_end_date',
        'old_subcontractor_id',
        'new_subcontractor_id',
        'change_reason',
        'conflicts_detected_count',
        'saved_with_override',
        'blocked_by_conflicts',
    ];

    protected function casts(): array
    {
        return [
            'old_start_date' => 'date',
            'new_start_date' => 'date',
            'old_end_date' => 'date',
            'new_end_date' => 'date',
            'conflicts_detected_count' => 'integer',
            'saved_with_override' => 'boolean',
            'blocked_by_conflicts' => 'boolean',
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

    public function timelineTask(): BelongsTo
    {
        return $this->belongsTo(TimelineTask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
