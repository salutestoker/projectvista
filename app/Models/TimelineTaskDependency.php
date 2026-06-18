<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TimelineTaskDependency extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'predecessor_task_id',
        'successor_task_id',
        'dependency_type',
        'lag_days',
        'lag_unit',
    ];

    protected function casts(): array
    {
        return [
            'lag_days' => 'integer',
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

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(TimelineTask::class, 'predecessor_task_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(TimelineTask::class, 'successor_task_id');
    }
}
