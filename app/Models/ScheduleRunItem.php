<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScheduleRunItem extends Model
{
    protected $fillable = [
        'schedule_run_id',
        'company_id',
        'project_id',
        'timeline_task_id',
        'assigned_subcontractor_id',
        'status',
        'readiness_status',
        'scheduled_start',
        'scheduled_end',
        'score',
        'score_breakdown',
        'block_reasons',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'date',
            'scheduled_end' => 'date',
            'score' => 'integer',
            'score_breakdown' => 'array',
            'block_reasons' => 'array',
        ];
    }

    public function scheduleRun(): BelongsTo
    {
        return $this->belongsTo(ScheduleRun::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TimelineTask::class, 'timeline_task_id');
    }

    public function assignedSubcontractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_subcontractor_id');
    }
}
