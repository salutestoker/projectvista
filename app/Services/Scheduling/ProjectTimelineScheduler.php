<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Company;
use App\Models\Project;
use App\Models\TimelineTask;
use App\Models\TimelineTaskTemplate;
use App\Models\TimelineTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ProjectTimelineScheduler
{
    private const DEFAULT_TEMPLATE_NAME = 'Standard Pool Build Timeline';

    private const DEFAULT_TASKS = [
        ['Contract Signed', 1, null, false, true],
        ['Permit Received', 1, null, false],
        ['Pre-Construction', 1, null, false],
        ['Layout', 1, 'Pool Construction', false],
        ['Excavation', 2, 'Excavation', false],
        ['Plumbing', 3, 'Plumbing', false],
        ['Electric', 2, 'Electrical', false],
        ['Steel', 3, 'Steel', false],
        ['Shotcrete', 1, 'Shotcrete', false],
        ['Tile', 5, 'Tile & Stone', false],
        ['Hardscape', 7, 'Hardscape', false],
        ['Interior', 2, 'Pool Construction', false],
        ['Startup', 2, 'Startup / Service', false],
    ];

    public function __construct(
        private WorkingDayCalendar $calendar,
        private ScheduleConflictDetector $conflictDetector,
    ) {}

    public function ensureDefaultTemplate(Company $company): TimelineTemplate
    {
        return DB::transaction(function () use ($company): TimelineTemplate {
            $timelineTemplate = TimelineTemplate::query()
                ->where('company_id', $company->id)
                ->where('is_default', true)
                ->orderBy('id')
                ->first();

            if ($timelineTemplate === null) {
                $timelineTemplate = TimelineTemplate::query()->create([
                    'company_id' => $company->id,
                    'name' => self::DEFAULT_TEMPLATE_NAME,
                    'description' => 'Default working-day sequence for ProjectVista pool construction schedules.',
                    'is_default' => true,
                ]);
            }

            if ($timelineTemplate->taskTemplates()->exists()) {
                $this->ensureContractSignedTemplateTask($timelineTemplate);

                return $timelineTemplate->refresh();
            }

            $subcontractorTypes = $company->subcontractorTypes()
                ->get()
                ->keyBy(fn ($type) => Str::lower((string) $type->name));

            foreach (self::DEFAULT_TASKS as $index => $task) {
                [$name, $duration, $typeName, $internalOnly] = $task;
                $isSystem = (bool) ($task[4] ?? false);
                $defaultType = $typeName === null ? null : $subcontractorTypes->get(Str::lower($typeName));

                TimelineTaskTemplate::query()->create([
                    'company_id' => $company->id,
                    'timeline_template_id' => $timelineTemplate->id,
                    'default_subcontractor_type_id' => $defaultType?->id,
                    'name' => $name,
                    'description' => 'Default project schedule milestone.',
                    'sequence_order' => $index + 1,
                    'default_duration_working_days' => $duration,
                    'internal_only' => $internalOnly,
                    'is_system' => $isSystem,
                ]);
            }

            $this->ensureContractSignedTemplateTask($timelineTemplate);

            return $timelineTemplate->refresh();
        });
    }

    public function createDefaultTimeline(Project $project): void
    {
        if ($project->contract_signed_on === null) {
            return;
        }

        if ($project->timelineTasks()->exists()) {
            $this->scheduleProjectIfUnscheduled($project);

            return;
        }

        $this->createTimelineFromTemplate(
            $project,
            $this->ensureDefaultTemplate($project->company()->firstOrFail()),
        );
    }

    public function createTimelineFromTemplate(Project $project, TimelineTemplate $timelineTemplate): void
    {
        if ($project->timelineTasks()->exists()) {
            return;
        }

        DB::transaction(function () use ($project, $timelineTemplate): void {
            $this->ensureContractSignedTemplateTask($timelineTemplate);
            $templates = $timelineTemplate->taskTemplates()->orderBy('sequence_order')->get();

            foreach ($templates as $template) {
                $this->createTaskFromTemplate($project, $timelineTemplate, $template);
            }

            if ($project->contract_signed_on !== null) {
                $this->scheduleProject($project->refresh());
            }
        });
    }

    /**
     * @return Collection<int, TimelineTask>
     */
    public function scheduleProject(Project $project, array $ignoredProjectIds = []): Collection
    {
        if ($project->contract_signed_on === null) {
            return collect();
        }

        $contractDate = CarbonImmutable::parse($project->contract_signed_on)->startOfDay();
        $tasks = $project->timelineTasks()
            ->orderByRaw('COALESCE(sequence_order, sort_order)')
            ->get();

        if ($tasks->isEmpty()) {
            return collect();
        }

        $previousEnd = null;

        foreach ($tasks as $task) {
            $sequenceOrder = $this->sequenceOrder($task);

            if ($this->isContractSignedTask($task)) {
                $task->forceFill([
                    'title' => 'Contract Signed',
                    'sort_order' => 1,
                    'sequence_order' => 1,
                    'default_duration_working_days' => 1,
                    'starts_on' => $contractDate,
                    'due_on' => $contractDate,
                    'completed_on' => $contractDate,
                    'status' => 'complete',
                    'is_system' => true,
                ])->save();

                $previousEnd = $contractDate;

                continue;
            }

            if ($task->status === 'complete') {
                $completedOn = $task->completed_on
                    ? CarbonImmutable::parse($task->completed_on)->startOfDay()
                    : CarbonImmutable::parse($task->due_on ?? $previousEnd ?? $contractDate)->startOfDay();
                $startsOn = $task->starts_on
                    ? CarbonImmutable::parse($task->starts_on)->startOfDay()
                    : null;
                $durationDays = $startsOn === null
                    ? max(1, (int) ($task->default_duration_working_days ?? 1))
                    : $this->calendar->countWorkingDaysInclusive($startsOn, $completedOn);

                $task->forceFill([
                    'completed_on' => $completedOn,
                    'due_on' => $startsOn === null ? ($task->due_on ?? $completedOn) : $completedOn,
                    'default_duration_working_days' => $durationDays,
                ])->save();

                $previousEnd = $completedOn;

                continue;
            }

            $ignoredTaskIds = $tasks
                ->filter(fn (TimelineTask $futureTask): bool => $this->sequenceOrder($futureTask) > $sequenceOrder)
                ->pluck('id')
                ->all();
            $range = $this->findFirstAvailableDateRange(
                $task,
                $previousEnd instanceof CarbonImmutable ? $previousEnd->addDay() : $contractDate->addDay(),
                $ignoredTaskIds,
                $ignoredProjectIds,
            );

            $task->forceFill([
                'starts_on' => $range->start,
                'due_on' => $range->end,
                'status' => $this->statusForRange($range, $task->status),
            ])->save();

            $previousEnd = $range->end;
        }

        return $tasks->fresh();
    }

    /**
     * @return Collection<int, Project>
     */
    public function scheduleCompanyProjects(Company $company): Collection
    {
        return $company->projects()
            ->whereNotNull('contract_signed_on')
            ->orderBy('contract_signed_on')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(fn (Project $project) => $this->createDefaultTimeline($project));
    }

    /**
     * @return Collection<int, Project>
     */
    public function rescheduleCompanyProjectsByPriority(Company $company): Collection
    {
        return DB::transaction(function () use ($company): Collection {
            $projects = $company->projects()
                ->whereNotNull('contract_signed_on')
                ->orderBy('contract_signed_on')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
            $projectIds = $projects->pluck('id')->values();

            $projects->each(function (Project $project, int $index) use ($projectIds): void {
                if (! $project->timelineTasks()->exists()) {
                    $this->createDefaultTimeline($project);
                }

                $ignoredProjectIds = $projectIds
                    ->slice($index + 1)
                    ->values()
                    ->all();

                $this->scheduleProject($project->refresh(), $ignoredProjectIds);
            });

            return $projects->map(fn (Project $project): Project => $project->fresh());
        });
    }

    private function scheduleProjectIfUnscheduled(Project $project): void
    {
        if (! $project->timelineTasks()
            ->where('status', 'not_scheduled')
            ->whereNull('starts_on')
            ->whereNull('due_on')
            ->exists()) {
            return;
        }

        $this->scheduleProject($project->refresh());
    }

    private function createTaskFromTemplate(
        Project $project,
        TimelineTemplate $timelineTemplate,
        TimelineTaskTemplate $template,
    ): TimelineTask {
        return TimelineTask::query()->create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'timeline_template_id' => $timelineTemplate->id,
            'timeline_task_template_id' => $template->id,
            'subcontractor_type_id' => $template->default_subcontractor_type_id,
            'title' => $template->name,
            'description' => $template->description,
            'sort_order' => $template->sequence_order,
            'sequence_order' => $template->sequence_order,
            'default_duration_working_days' => $template->default_duration_working_days,
            'status' => 'not_scheduled',
            'internal_only' => $template->internal_only,
            'is_system' => $template->is_system,
            'requires_acknowledgement' => false,
            'is_job_site_ready' => true,
            'are_materials_ready' => true,
            'is_customer_approval_required' => false,
            'is_customer_approval_received' => false,
        ]);
    }

    public function calculatePermitDate(CarbonImmutable|string $contractDate): CarbonImmutable
    {
        return $this->calendar->addWorkingDays($contractDate, 30);
    }

    /**
     * @param  list<int>  $ignoredTaskIds
     * @param  list<int>  $ignoredProjectIds
     */
    public function findFirstAvailableDateRange(
        TimelineTask $task,
        CarbonImmutable|string $earliestStart,
        array $ignoredTaskIds = [],
        array $ignoredProjectIds = [],
    ): DateRange {
        $candidateStart = $this->calendar->nextWorkingDay($earliestStart);
        $attempts = 0;
        $durationDays = max(1, (int) ($task->default_duration_working_days ?: 1));

        do {
            $range = $this->calendar->workingDateRange($candidateStart, $durationDays);
            $conflicts = $this->conflictDetector->detectForProposedChange($task, [
                'starts_on' => $range->start->toDateString(),
                'due_on' => $range->end->toDateString(),
                'duration_days' => $durationDays,
                'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
                'subcontractor_type_id' => $task->subcontractor_type_id,
                'status' => $task->status,
                'is_job_site_ready' => true,
                'are_materials_ready' => true,
                'is_customer_approval_required' => false,
                'is_customer_approval_received' => false,
                'ignore_task_ids' => $ignoredTaskIds,
                'ignore_project_ids' => $ignoredProjectIds,
                'allow_non_working_days' => $durationDays > 5,
            ]);

            if ($conflicts->isEmpty()) {
                return $range;
            }

            $candidateStart = $this->calendar->nextWorkingDay($candidateStart->addDay());
            $attempts++;
        } while ($attempts < 366);

        return $this->calendar->workingDateRange($candidateStart, $durationDays);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function previewReschedule(TimelineTask $task, array $attributes): SchedulePreviewResult
    {
        return new SchedulePreviewResult($this->conflictDetector->detectForProposedChange($task, $attributes));
    }

    public function insertTaskAfter(TimelineTask $task, TimelineTask $predecessor, bool $rescheduleProject = true): TimelineTask
    {
        $project = $task->project()->firstOrFail();
        $nextOrder = $this->sequenceOrder($predecessor) + 1;

        DB::transaction(function () use ($project, $task, $nextOrder, $rescheduleProject): void {
            TimelineTask::query()
                ->where('project_id', $project->id)
                ->whereKeyNot($task->id)
                ->whereRaw('COALESCE(sequence_order, sort_order) >= ?', [$nextOrder])
                ->update([
                    'sequence_order' => DB::raw('COALESCE(sequence_order, sort_order) + 1'),
                    'sort_order' => DB::raw('sort_order + 1'),
                ]);

            $task->forceFill([
                'sequence_order' => $nextOrder,
                'sort_order' => $nextOrder,
            ])->save();

            if ($rescheduleProject) {
                $this->scheduleProject($project->refresh());
            }
        });

        return $task->fresh();
    }

    public function normalizeProjectTaskOrder(Project $project): void
    {
        $project->timelineTasks()
            ->orderByRaw('COALESCE(sequence_order, sort_order)')
            ->get()
            ->values()
            ->each(function (TimelineTask $task, int $index): void {
                $order = $index + 1;

                $task->forceFill([
                    'sequence_order' => $order,
                    'sort_order' => $order,
                ])->save();
            });
    }

    public function ensureContractSignedTemplateTask(TimelineTemplate $timelineTemplate): TimelineTaskTemplate
    {
        return DB::transaction(function () use ($timelineTemplate): TimelineTaskTemplate {
            $taskTemplates = $timelineTemplate->taskTemplates()
                ->orderByRaw('COALESCE(sequence_order, 999999)')
                ->get();
            $systemTask = $taskTemplates->first(fn (TimelineTaskTemplate $taskTemplate): bool => $this->isContractSignedTemplateTask($taskTemplate));

            if ($systemTask === null) {
                $systemTask = TimelineTaskTemplate::query()->create([
                    'company_id' => $timelineTemplate->company_id,
                    'timeline_template_id' => $timelineTemplate->id,
                    'default_subcontractor_type_id' => null,
                    'name' => 'Contract Signed',
                    'description' => 'Project contract milestone tied to the project contract signed date.',
                    'sequence_order' => max(1, ($taskTemplates->max('sequence_order') ?? 0) + 1),
                    'default_duration_working_days' => 1,
                    'internal_only' => false,
                    'is_system' => true,
                ]);

                $taskTemplates = $timelineTemplate->taskTemplates()
                    ->orderByRaw('COALESCE(sequence_order, 999999)')
                    ->get();
            }

            $taskTemplates
                ->where('id', '!=', $systemTask->id)
                ->values()
                ->each(function (TimelineTaskTemplate $taskTemplate, int $index): void {
                    $taskTemplate->forceFill(['sequence_order' => 10000 + $index])->save();
                });

            $systemTask->forceFill([
                'default_subcontractor_type_id' => null,
                'name' => 'Contract Signed',
                'description' => $systemTask->description ?: 'Project contract milestone tied to the project contract signed date.',
                'sequence_order' => 1,
                'default_duration_working_days' => 1,
                'internal_only' => false,
                'is_system' => true,
            ])->save();

            $timelineTemplate->taskTemplates()
                ->whereKeyNot($systemTask->id)
                ->orderBy('sequence_order')
                ->get()
                ->values()
                ->each(function (TimelineTaskTemplate $taskTemplate, int $index): void {
                    $taskTemplate->forceFill([
                        'sequence_order' => $index + 2,
                        'is_system' => false,
                    ])->save();
                });

            return $systemTask->refresh();
        });
    }

    private function statusForRange(DateRange $range, string $currentStatus): string
    {
        $today = CarbonImmutable::today();

        if (in_array($currentStatus, ['blocked', 'needs_approval', 'delayed'], true)) {
            return $currentStatus;
        }

        if ($range->start->lessThanOrEqualTo($today)) {
            return 'in_progress';
        }

        return 'upcoming';
    }

    private function sequenceOrder(TimelineTask $task): int
    {
        return (int) ($task->sequence_order ?? $task->sort_order ?? 0);
    }

    private function isContractSignedTask(TimelineTask $task): bool
    {
        return $task->is_system || Str::lower($task->title) === 'contract signed' || $this->sequenceOrder($task) === 1;
    }

    private function isContractSignedTemplateTask(TimelineTaskTemplate $taskTemplate): bool
    {
        return $taskTemplate->is_system || Str::lower($taskTemplate->name) === 'contract signed' || $taskTemplate->sequence_order === 1;
    }
}
