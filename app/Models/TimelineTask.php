<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TimelineTask extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'timeline_template_id',
        'timeline_task_template_id',
        'assigned_subcontractor_id',
        'subcontractor_type_id',
        'created_by_id',
        'updated_by_id',
        'title',
        'phase',
        'description',
        'sort_order',
        'sequence_order',
        'default_duration_working_days',
        'priority',
        'customer_urgency',
        'schedule_score',
        'score_breakdown',
        'is_schedule_locked',
        'schedule_locked_reason',
        'uses_calendar_days',
        'last_scheduled_at',
        'internal_only',
        'is_system',
        'status',
        'readiness_status',
        'ready_since',
        'starts_on',
        'due_on',
        'completed_on',
        'actual_start_date',
        'actual_end_date',
        'requires_acknowledgement',
        'is_job_site_ready',
        'are_materials_ready',
        'is_customer_approval_required',
        'is_customer_approval_received',
        'internal_notes',
        'customer_notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'due_on' => 'date',
            'completed_on' => 'date',
            'ready_since' => 'datetime',
            'last_scheduled_at' => 'datetime',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'score_breakdown' => 'array',
            'internal_only' => 'boolean',
            'is_system' => 'boolean',
            'is_schedule_locked' => 'boolean',
            'uses_calendar_days' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'is_job_site_ready' => 'boolean',
            'are_materials_ready' => 'boolean',
            'is_customer_approval_required' => 'boolean',
            'is_customer_approval_received' => 'boolean',
            'sort_order' => 'integer',
            'sequence_order' => 'integer',
            'default_duration_working_days' => 'integer',
            'priority' => 'integer',
            'customer_urgency' => 'integer',
            'schedule_score' => 'integer',
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

    public function assignedSubcontractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_subcontractor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function subcontractorType(): BelongsTo
    {
        return $this->belongsTo(SubcontractorType::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TimelineTemplate::class, 'timeline_template_id');
    }

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TimelineTaskTemplate::class, 'timeline_task_template_id');
    }

    public function predecessorDependencies(): HasMany
    {
        return $this->hasMany(TimelineTaskDependency::class, 'successor_task_id');
    }

    public function successorDependencies(): HasMany
    {
        return $this->hasMany(TimelineTaskDependency::class, 'predecessor_task_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TimelineTaskBlock::class);
    }

    public function activeBlocks(): HasMany
    {
        return $this->hasMany(TimelineTaskBlock::class)->where('status', 'active');
    }

    public function scheduleRunItems(): HasMany
    {
        return $this->hasMany(ScheduleRunItem::class);
    }
}
