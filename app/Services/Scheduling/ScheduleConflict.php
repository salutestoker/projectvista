<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

final readonly class ScheduleConflict
{
    public function __construct(
        public string $type,
        public string $label,
        public string $severity,
        public string $projectName,
        public ?string $conflictingProjectName,
        public string $taskTitle,
        public ?int $taskId,
        public ?int $conflictingTaskId,
        public ?string $dateRange,
        public ?string $conflictDate,
        public ?string $subcontractorName,
        public ?string $subcontractorTypeName,
        public string $reason,
        public string $suggestedResolution,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'severity' => $this->severity,
            'project_name' => $this->projectName,
            'project_conflict' => $this->conflictingProjectName ?? $this->projectName,
            'conflicting_project_name' => $this->conflictingProjectName,
            'task_title' => $this->taskTitle,
            'task_id' => $this->taskId,
            'conflicting_task_id' => $this->conflictingTaskId,
            'date_range' => $this->dateRange ?? 'TBD',
            'conflict_date' => $this->conflictDate,
            'subcontractor_name' => $this->subcontractorName,
            'subcontractor_type_name' => $this->subcontractorTypeName,
            'reason' => $this->reason,
            'suggested_resolution' => $this->suggestedResolution,
        ];
    }
}
