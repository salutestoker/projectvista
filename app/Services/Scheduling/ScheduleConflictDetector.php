<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Project;
use App\Models\TimelineTask;
use App\Support\ProjectVista\TimelineScheduler;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class ScheduleConflictDetector
{
    public function __construct(private WorkingDayCalendar $calendar) {}

    /**
     * @return Collection<int, ScheduleConflict>
     */
    public function detectForTask(TimelineTask $task): Collection
    {
        return $this->detectForProposedChange($task, [
            'starts_on' => $task->starts_on?->toDateString(),
            'due_on' => $task->due_on?->toDateString(),
            'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
            'subcontractor_type_id' => $task->subcontractor_type_id,
            'status' => $task->status,
            'is_job_site_ready' => $task->is_job_site_ready,
            'are_materials_ready' => $task->are_materials_ready,
            'is_customer_approval_required' => $task->is_customer_approval_required,
            'is_customer_approval_received' => $task->is_customer_approval_received,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, ScheduleConflict>
     */
    public function detectForProposedChange(TimelineTask $task, array $attributes): Collection
    {
        $task->loadMissing(['project', 'assignedSubcontractor', 'subcontractorType']);

        $project = $task->project;
        $startsOn = $this->dateOrNull($attributes['starts_on'] ?? null);
        $dueOn = $this->dateOrNull($attributes['due_on'] ?? null);
        $ignoredTaskIds = collect($attributes['ignore_task_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        if ($project === null) {
            return collect();
        }

        $ignoredProjectIds = collect($attributes['ignore_project_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== (int) $project->id)
            ->values();
        $durationDays = (int) ($attributes['duration_days']
            ?? $attributes['default_duration_working_days']
            ?? $task->default_duration_working_days
            ?? 1);
        $allowNonWorkingDays = filter_var($attributes['allow_non_working_days'] ?? false, FILTER_VALIDATE_BOOL)
            || $durationDays > 5
            || $this->allowsNonWorkingDayScheduling($task);

        $conflicts = collect();

        if ($startsOn !== null && $dueOn !== null) {
            $range = new DateRange($startsOn, $dueOn);
            $assignedSubcontractorId = $this->nullableInt($attributes['assigned_subcontractor_id'] ?? null);
            $subcontractorTypeId = $this->nullableInt($attributes['subcontractor_type_id'] ?? null);

            $conflicts = $conflicts
                ->merge($this->detectSubcontractorDoubleBooking($task, $project, $range, $assignedSubcontractorId, $ignoredTaskIds, $ignoredProjectIds))
                ->merge($this->detectSameProjectTradeConflict($task, $project, $range, $subcontractorTypeId, $ignoredTaskIds, $ignoredProjectIds))
                ->merge($this->detectSequenceConflict($task, $project, $range, $ignoredTaskIds))
                ->merge($allowNonWorkingDays ? collect() : $this->detectNonWorkingDayConflict($task, $project, $range));
        }

        return $conflicts
            ->merge($this->detectReadinessConflict($task, $project, $attributes))
            ->unique(fn (ScheduleConflict $conflict): string => implode('|', [
                $conflict->type,
                $conflict->conflictingProjectName,
                $conflict->taskTitle,
                $conflict->conflictDate,
                $conflict->reason,
            ]))
            ->values();
    }

    /**
     * @return Collection<int, ScheduleConflict>
     */
    private function detectSubcontractorDoubleBooking(
        TimelineTask $task,
        Project $project,
        DateRange $range,
        ?int $assignedSubcontractorId,
        Collection $ignoredTaskIds,
        Collection $ignoredProjectIds,
    ): Collection {
        if ($assignedSubcontractorId === null) {
            return collect();
        }

        return $this->overlappingTasks($project, $range, $task, $ignoredTaskIds, $ignoredProjectIds)
            ->where('assigned_subcontractor_id', $assignedSubcontractorId)
            ->get()
            ->map(function (TimelineTask $conflict) use ($task, $project, $range): ScheduleConflict {
                $conflict->loadMissing(['project', 'assignedSubcontractor', 'subcontractorType']);
                $conflictDate = $this->firstOverlapDate($range, DateRange::from($conflict->starts_on, $conflict->due_on));
                $subcontractor = $conflict->assignedSubcontractor ?? $task->assignedSubcontractor;

                return new ScheduleConflict(
                    type: 'subcontractor_double_booked',
                    label: 'Subcontractor Double-Booked',
                    severity: 'blocking',
                    projectName: $project->name,
                    conflictingProjectName: $conflict->project?->name,
                    taskTitle: $conflict->title,
                    taskId: $task->id,
                    conflictingTaskId: $conflict->id,
                    dateRange: $this->shortDateRange($conflict->starts_on, $conflict->due_on),
                    conflictDate: $conflictDate?->toDateString(),
                    subcontractorName: $subcontractor?->name,
                    subcontractorTypeName: $conflict->subcontractorType?->name,
                    reason: sprintf(
                        '%s is already scheduled for %s on %s.',
                        $subcontractor?->name ?? 'This subcontractor',
                        $conflict->project?->name ?? 'another project',
                        $conflictDate?->format('M j') ?? 'the selected dates',
                    ),
                    suggestedResolution: 'Change the assigned subcontractor or move the date range.',
                );
            });
    }

    /**
     * @return Collection<int, ScheduleConflict>
     */
    private function detectSameProjectTradeConflict(
        TimelineTask $task,
        Project $project,
        DateRange $range,
        ?int $subcontractorTypeId,
        Collection $ignoredTaskIds,
        Collection $ignoredProjectIds,
    ): Collection {
        if ($subcontractorTypeId === null) {
            return collect();
        }

        return $this->overlappingTasks($project, $range, $task, $ignoredTaskIds, $ignoredProjectIds)
            ->where('project_id', $project->id)
            ->whereNotNull('subcontractor_type_id')
            ->where('subcontractor_type_id', '!=', $subcontractorTypeId)
            ->get()
            ->map(function (TimelineTask $conflict) use ($task, $project, $range): ScheduleConflict {
                $conflict->loadMissing(['project', 'assignedSubcontractor', 'subcontractorType']);
                $task->loadMissing('subcontractorType');
                $conflictDate = $this->firstOverlapDate($range, DateRange::from($conflict->starts_on, $conflict->due_on));

                return new ScheduleConflict(
                    type: 'same_day_project_conflict',
                    label: 'Same-Day Project Conflict',
                    severity: 'override_allowed',
                    projectName: $project->name,
                    conflictingProjectName: $project->name,
                    taskTitle: $conflict->title,
                    taskId: $task->id,
                    conflictingTaskId: $conflict->id,
                    dateRange: $this->shortDateRange($conflict->starts_on, $conflict->due_on),
                    conflictDate: $conflictDate?->toDateString(),
                    subcontractorName: $conflict->assignedSubcontractor?->name,
                    subcontractorTypeName: $conflict->subcontractorType?->name,
                    reason: sprintf(
                        '%s and %s are both scheduled on %s.',
                        $task->subcontractorType?->name ?? 'The selected trade',
                        $conflict->subcontractorType?->name ?? 'another trade',
                        $conflictDate?->format('M j') ?? 'the selected dates',
                    ),
                    suggestedResolution: 'Move one task to another working day before saving.',
                );
            });
    }

    /**
     * @return Collection<int, ScheduleConflict>
     */
    private function detectSequenceConflict(TimelineTask $task, Project $project, DateRange $range, Collection $ignoredTaskIds): Collection
    {
        $sequenceOrder = $task->sequence_order ?? $task->sort_order;

        if ($sequenceOrder === null) {
            return collect();
        }

        $conflicts = collect();

        $previousTask = TimelineTask::query()
            ->where('project_id', $project->id)
            ->whereNotNull('due_on')
            ->where(fn (Builder $query) => $query
                ->where('sequence_order', '<', $sequenceOrder)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('sequence_order')
                    ->where('sort_order', '<', $sequenceOrder)))
            ->when($task->exists, fn (Builder $query) => $query->whereKeyNot($task->id))
            ->when($ignoredTaskIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $ignoredTaskIds))
            ->orderByRaw('COALESCE(sequence_order, sort_order) desc')
            ->first();

        if ($previousTask !== null && $previousTask->due_on !== null && $range->start->lessThanOrEqualTo($previousTask->due_on)) {
            $conflicts->push(new ScheduleConflict(
                type: 'task_sequence_conflict',
                label: 'Task Sequence Conflict',
                severity: 'blocking',
                projectName: $project->name,
                conflictingProjectName: $project->name,
                taskTitle: $task->title,
                taskId: $task->id,
                conflictingTaskId: $previousTask->id,
                dateRange: $this->shortDateRange($previousTask->starts_on, $previousTask->due_on),
                conflictDate: $range->start->toDateString(),
                subcontractorName: $task->assignedSubcontractor?->name,
                subcontractorTypeName: $task->subcontractorType?->name,
                reason: sprintf('%s cannot start before %s ends.', $task->title, $previousTask->title),
                suggestedResolution: 'Move this task after the previous sequence task.',
            ));
        }

        $nextTask = TimelineTask::query()
            ->where('project_id', $project->id)
            ->whereNotNull('starts_on')
            ->where(fn (Builder $query) => $query
                ->where('sequence_order', '>', $sequenceOrder)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('sequence_order')
                    ->where('sort_order', '>', $sequenceOrder)))
            ->when($task->exists, fn (Builder $query) => $query->whereKeyNot($task->id))
            ->when($ignoredTaskIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $ignoredTaskIds))
            ->orderByRaw('COALESCE(sequence_order, sort_order)')
            ->first();

        if ($nextTask !== null && $nextTask->starts_on !== null && $range->end->greaterThanOrEqualTo($nextTask->starts_on)) {
            $conflicts->push(new ScheduleConflict(
                type: 'task_sequence_conflict',
                label: 'Task Sequence Conflict',
                severity: 'blocking',
                projectName: $project->name,
                conflictingProjectName: $project->name,
                taskTitle: $task->title,
                taskId: $task->id,
                conflictingTaskId: $nextTask->id,
                dateRange: $this->shortDateRange($nextTask->starts_on, $nextTask->due_on),
                conflictDate: $range->end->toDateString(),
                subcontractorName: $task->assignedSubcontractor?->name,
                subcontractorTypeName: $task->subcontractorType?->name,
                reason: sprintf('%s cannot end after %s starts.', $task->title, $nextTask->title),
                suggestedResolution: 'Move this task earlier or move the next sequence task later.',
            ));
        }

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, ScheduleConflict>
     */
    private function detectReadinessConflict(TimelineTask $task, Project $project, array $attributes): Collection
    {
        $status = (string) ($attributes['status'] ?? $task->status);

        if ($status === 'not_scheduled') {
            return collect();
        }

        $jobSiteReady = filter_var($attributes['is_job_site_ready'] ?? $task->is_job_site_ready, FILTER_VALIDATE_BOOL);
        $materialsReady = filter_var($attributes['are_materials_ready'] ?? $task->are_materials_ready, FILTER_VALIDATE_BOOL);
        $approvalRequired = filter_var($attributes['is_customer_approval_required'] ?? $task->is_customer_approval_required, FILTER_VALIDATE_BOOL);
        $approvalReceived = filter_var($attributes['is_customer_approval_received'] ?? $task->is_customer_approval_received, FILTER_VALIDATE_BOOL);
        $conflicts = collect();

        if (! $jobSiteReady) {
            $conflicts->push(new ScheduleConflict(
                type: 'job_site_not_ready',
                label: 'Job Site Not Ready',
                severity: 'blocking',
                projectName: $project->name,
                conflictingProjectName: $project->name,
                taskTitle: $task->title,
                taskId: $task->id,
                conflictingTaskId: null,
                dateRange: $this->shortDateRange($task->starts_on, $task->due_on),
                conflictDate: $task->starts_on?->toDateString(),
                subcontractorName: $task->assignedSubcontractor?->name,
                subcontractorTypeName: $task->subcontractorType?->name,
                reason: 'The job site is not marked ready for this task.',
                suggestedResolution: 'Mark the job site ready or move the task later.',
            ));
        }

        if (! $materialsReady) {
            $conflicts->push(new ScheduleConflict(
                type: 'materials_not_ready',
                label: 'Materials Not Ready',
                severity: 'warning',
                projectName: $project->name,
                conflictingProjectName: $project->name,
                taskTitle: $task->title,
                taskId: $task->id,
                conflictingTaskId: null,
                dateRange: $this->shortDateRange($task->starts_on, $task->due_on),
                conflictDate: $task->starts_on?->toDateString(),
                subcontractorName: $task->assignedSubcontractor?->name,
                subcontractorTypeName: $task->subcontractorType?->name,
                reason: 'Materials are not marked ready for this task.',
                suggestedResolution: 'Confirm materials or move the task later.',
            ));
        }

        if ($approvalRequired && ! $approvalReceived) {
            $conflicts->push(new ScheduleConflict(
                type: 'customer_approval_missing',
                label: 'Customer Approval Missing',
                severity: 'blocking',
                projectName: $project->name,
                conflictingProjectName: $project->name,
                taskTitle: $task->title,
                taskId: $task->id,
                conflictingTaskId: null,
                dateRange: $this->shortDateRange($task->starts_on, $task->due_on),
                conflictDate: $task->starts_on?->toDateString(),
                subcontractorName: $task->assignedSubcontractor?->name,
                subcontractorTypeName: $task->subcontractorType?->name,
                reason: 'Customer approval is required but has not been received.',
                suggestedResolution: 'Collect the approval before scheduling this task.',
            ));
        }

        return $conflicts;
    }

    /**
     * @return Collection<int, ScheduleConflict>
     */
    private function detectNonWorkingDayConflict(TimelineTask $task, Project $project, DateRange $range): Collection
    {
        return collect(CarbonPeriod::create($range->start, $range->end))
            ->filter(fn (CarbonInterface $date): bool => ! $this->calendar->isWorkingDay($date))
            ->map(fn (CarbonInterface $date): ScheduleConflict => new ScheduleConflict(
                type: 'non_working_day',
                label: 'Non-Working Day',
                severity: 'override_allowed',
                projectName: $project->name,
                conflictingProjectName: $project->name,
                taskTitle: $task->title,
                taskId: $task->id,
                conflictingTaskId: null,
                dateRange: $this->shortDateRange($range->start, $range->end),
                conflictDate: $date->toDateString(),
                subcontractorName: $task->assignedSubcontractor?->name,
                subcontractorTypeName: $task->subcontractorType?->name,
                reason: sprintf('%s falls on a non-working day.', $date->format('M j')),
                suggestedResolution: 'Move the task to Monday through Friday.',
            ));
    }

    private function overlappingTasks(
        Project $project,
        DateRange $range,
        TimelineTask $task,
        Collection $ignoredTaskIds,
        Collection $ignoredProjectIds,
    ): Builder {
        return TimelineTask::query()
            ->with(['project', 'assignedSubcontractor', 'subcontractorType'])
            ->where('company_id', $project->company_id)
            ->whereNotIn('status', TimelineScheduler::CLOSED_STATUSES)
            ->whereNotNull('starts_on')
            ->whereNotNull('due_on')
            ->when($task->exists, fn (Builder $query) => $query->whereKeyNot($task->id))
            ->when($ignoredTaskIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $ignoredTaskIds))
            ->when($ignoredProjectIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('project_id', $ignoredProjectIds))
            ->where(fn (Builder $query) => $query
                ->whereDate('starts_on', '<=', $range->end)
                ->whereDate('due_on', '>=', $range->start));
    }

    private function allowsNonWorkingDayScheduling(TimelineTask $task): bool
    {
        return $task->is_system
            || Str::lower($task->title) === 'contract signed';
    }

    private function firstOverlapDate(DateRange $first, DateRange $second): ?CarbonImmutable
    {
        if (! $first->overlaps($second)) {
            return null;
        }

        return $first->start->greaterThan($second->start) ? $first->start : $second->start;
    }

    private function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function shortDateRange(CarbonInterface|string|null $startsOn, CarbonInterface|string|null $dueOn): string
    {
        if ($startsOn === null && $dueOn === null) {
            return 'TBD';
        }

        $start = $startsOn === null ? null : CarbonImmutable::parse($startsOn);
        $due = $dueOn === null ? null : CarbonImmutable::parse($dueOn);

        if ($start !== null && $due !== null && $start->isSameDay($due)) {
            return $start->format('M j');
        }

        return collect([$start, $due])
            ->filter()
            ->map(fn (CarbonImmutable $date) => $date->format('M j'))
            ->join(' - ');
    }
}
