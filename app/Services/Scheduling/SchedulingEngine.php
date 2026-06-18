<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Company;
use App\Models\ScheduleRun;
use App\Models\TimelineTask;
use App\Models\TimelineTaskBlock;
use App\Models\TimelineTaskDependency;
use App\Support\ProjectVista\Roles;
use App\Support\ProjectVista\TimelineScheduler;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class SchedulingEngine
{
    private const HORIZON_DAYS = 540;

    public function __construct(private WorkingDayCalendar $calendar) {}

    public function scheduleCompany(Company $company, ?int $triggeredById = null): ScheduleRun
    {
        return DB::transaction(function () use ($company, $triggeredById): ScheduleRun {
            $run = ScheduleRun::query()->create([
                'company_id' => $company->id,
                'triggered_by_id' => $triggeredById,
                'status' => 'running',
                'started_at' => now(),
                'summary' => [],
            ]);

            $projects = $company->projects()
                ->whereNotNull('contract_signed_on')
                ->with([
                    'timelineTasks.project',
                    'timelineTasks.subcontractorType',
                    'timelineTasks.activeBlocks',
                    'timelineTasks.predecessorDependencies.predecessor',
                    'timelineTasks.successorDependencies.successor',
                ])
                ->orderBy('contract_signed_on')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $tasks = $projects
                ->flatMap->timelineTasks
                ->sortBy([
                    fn (TimelineTask $task): string => $task->project?->contract_signed_on?->toDateString() ?? '9999-12-31',
                    fn (TimelineTask $task): int => (int) ($task->sequence_order ?? $task->sort_order ?? 0),
                    fn (TimelineTask $task): int => (int) $task->id,
                ])
                ->values();

            $subcontractors = $this->subcontractors($company);
            $occupancy = [];

            $tasks->each(function (TimelineTask $task) use (&$occupancy): void {
                $task->status = $this->normalizeStatus($task->status);

                if ($task->status === 'complete') {
                    $this->normalizeCompletedTask($task);
                }

                if ($this->consumesLockedCapacity($task)) {
                    $this->reserveCapacity($occupancy, (int) $task->assigned_subcontractor_id, DateRange::from($task->starts_on, $task->due_on));
                }
            });

            $this->clearUnlockedSchedule($tasks);

            $scheduledTaskIds = collect();
            $attempts = 0;
            $maxAttempts = max(1, $tasks->count() * 3);

            while ($attempts < $maxAttempts) {
                $attempts++;
                $candidates = $this->candidateTasks($tasks, $subcontractors, $scheduledTaskIds);

                if ($candidates->isEmpty()) {
                    break;
                }

                $winner = $candidates
                    ->sort(function (array $first, array $second): int {
                        $score = $second['score'] <=> $first['score'];

                        if ($score !== 0) {
                            return $score;
                        }

                        $date = strcmp($first['earliest_start']->toDateString(), $second['earliest_start']->toDateString());

                        if ($date !== 0) {
                            return $date;
                        }

                        $firstContract = $first['task']->project?->contract_signed_on?->toDateString() ?? '9999-12-31';
                        $secondContract = $second['task']->project?->contract_signed_on?->toDateString() ?? '9999-12-31';
                        $contract = strcmp($firstContract, $secondContract);

                        if ($contract !== 0) {
                            return $contract;
                        }

                        $sequence = ((int) ($first['task']->sequence_order ?? $first['task']->sort_order ?? 0))
                            <=> ((int) ($second['task']->sequence_order ?? $second['task']->sort_order ?? 0));

                        return $sequence !== 0 ? $sequence : ((int) $first['task']->id <=> (int) $second['task']->id);
                    })
                    ->first();

                if (! is_array($winner)) {
                    break;
                }

                /** @var TimelineTask $task */
                $task = $winner['task'];
                $assignment = $this->findAssignment($task, $winner['earliest_start'], $subcontractors, $occupancy, $tasks);

                if ($assignment === null) {
                    $this->markBlocked($task, ['Required trade has no available capacity in the scheduling horizon.']);
                    $this->recordRunItem($run, $task, ['resource_unavailable'], 'No available subcontractor capacity found.');
                    $scheduledTaskIds->push($task->id);

                    continue;
                }

                $scoreBreakdown = $this->scoreBreakdown($task, $winner['earliest_start'], $assignment['subcontractor_id']);
                $score = (int) array_sum($scoreBreakdown);
                $status = $this->statusForRange($assignment['range'], $winner['readiness_status']);

                $task->forceFill([
                    'assigned_subcontractor_id' => $assignment['subcontractor_id'],
                    'starts_on' => $assignment['range']->start,
                    'due_on' => $assignment['range']->end,
                    'status' => $status,
                    'readiness_status' => $winner['readiness_status'],
                    'ready_since' => $winner['readiness_status'] === 'ready'
                        ? ($task->ready_since ?? now())
                        : $task->ready_since,
                    'schedule_score' => $score,
                    'score_breakdown' => $scoreBreakdown,
                    'last_scheduled_at' => now(),
                ])->save();

                if ($assignment['subcontractor_id'] !== null) {
                    $this->reserveCapacity($occupancy, $assignment['subcontractor_id'], $assignment['range']);
                }

                $this->recordRunItem(
                    $run,
                    $task,
                    [],
                    sprintf(
                        '%s scheduled %s for %s with score %d.',
                        $task->title,
                        $assignment['subcontractor_id'] === null ? 'without a subcontractor' : 'to subcontractor #'.$assignment['subcontractor_id'],
                        $this->shortDateRange($assignment['range']),
                        $score,
                    ),
                );

                $scheduledTaskIds->push($task->id);
            }

            $this->markRemaining($run, $tasks, $subcontractors, $scheduledTaskIds);
            $this->updateProjects($projects);

            $run->update([
                'status' => 'complete',
                'finished_at' => now(),
                'summary' => [
                    'projects' => $projects->count(),
                    'tasks' => $tasks->count(),
                    'scheduled' => $tasks->whereIn('status', ['scheduled', 'ready', 'in_progress'])->count(),
                    'blocked' => $tasks->where('status', 'blocked')->count(),
                    'complete' => $tasks->where('status', 'complete')->count(),
                ],
            ]);

            return $run->refresh();
        });
    }

    /**
     * @param  Collection<int, TimelineTask>  $tasks
     */
    private function clearUnlockedSchedule(Collection $tasks): void
    {
        $tasks
            ->reject(fn (TimelineTask $task): bool => $task->is_system
                || $task->is_schedule_locked
                || in_array($task->status, ['complete', 'cancelled'], true)
                || ($task->status === 'in_progress' && $task->actual_start_date !== null))
            ->each(function (TimelineTask $task): void {
                $task->forceFill([
                    'starts_on' => null,
                    'due_on' => null,
                    'assigned_subcontractor_id' => null,
                    'status' => 'not_scheduled',
                    'readiness_status' => 'not_ready',
                    'schedule_score' => 0,
                    'score_breakdown' => null,
                    'last_scheduled_at' => null,
                ])->save();
            });
    }

    /**
     * @param  Collection<int, TimelineTask>  $tasks
     * @param  Collection<int, array<string, mixed>>  $subcontractors
     * @param  Collection<int, int>  $scheduledTaskIds
     * @return Collection<int, array<string, mixed>>
     */
    private function candidateTasks(Collection $tasks, Collection $subcontractors, Collection $scheduledTaskIds): Collection
    {
        return $tasks
            ->reject(fn (TimelineTask $task): bool => $scheduledTaskIds->contains($task->id)
                || $task->is_system
                || $task->is_schedule_locked
                || in_array($task->status, ['complete', 'cancelled'], true)
                || ($task->status === 'in_progress' && $task->actual_start_date !== null))
            ->map(function (TimelineTask $task) use ($subcontractors): ?array {
                $blockReasons = $this->blockReasons($task, $subcontractors);

                if ($blockReasons !== []) {
                    $this->markBlocked($task, $blockReasons);

                    return null;
                }

                $dependencyWindow = $this->dependencyWindow($task);

                if ($dependencyWindow['blocked']) {
                    $task->forceFill([
                        'status' => 'not_scheduled',
                        'readiness_status' => 'waiting_dependencies',
                        'schedule_score' => 0,
                        'score_breakdown' => null,
                    ])->save();

                    return null;
                }

                /** @var CarbonImmutable $earliestStart */
                $earliestStart = $dependencyWindow['earliest_start'];
                $readinessStatus = $dependencyWindow['ready'] ? 'ready' : 'waiting_dependencies';
                $scoreBreakdown = $this->scoreBreakdown($task, $earliestStart, null);

                return [
                    'task' => $task,
                    'earliest_start' => $earliestStart,
                    'readiness_status' => $readinessStatus,
                    'score' => (int) array_sum($scoreBreakdown),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $subcontractors
     * @return list<string>
     */
    private function blockReasons(TimelineTask $task, Collection $subcontractors): array
    {
        $reasons = $task->activeBlocks
            ->map(fn (TimelineTaskBlock $block): string => $block->title)
            ->values()
            ->all();

        if (! $task->is_job_site_ready) {
            $reasons[] = 'Job site is not ready.';
        }

        if (! $task->are_materials_ready) {
            $reasons[] = 'Materials are not ready.';
        }

        if ($task->is_customer_approval_required && ! $task->is_customer_approval_received) {
            $reasons[] = 'Customer approval is required.';
        }

        if ($task->subcontractor_type_id !== null && $subcontractors
            ->where('subcontractor_type_id', (int) $task->subcontractor_type_id)
            ->where('scheduling_is_active', true)
            ->isEmpty()) {
            $reasons[] = 'No active subcontractor is available for this trade.';
        }

        return $reasons;
    }

    /**
     * @return array{blocked: bool, ready: bool, earliest_start: CarbonImmutable}
     */
    private function dependencyWindow(TimelineTask $task): array
    {
        $projectStart = CarbonImmutable::parse($task->project?->contract_signed_on ?? now())->startOfDay()->addDay();
        $earliestStart = $this->calendar->nextWorkingDay($projectStart);
        $ready = true;

        $task->loadMissing('predecessorDependencies.predecessor');

        foreach ($task->predecessorDependencies as $dependency) {
            $predecessor = $dependency->predecessor;

            if ($predecessor === null || in_array($predecessor->status, ['blocked', 'cancelled'], true)) {
                return ['blocked' => true, 'ready' => false, 'earliest_start' => $earliestStart];
            }

            $anchor = $this->dependencyAnchor($dependency, $predecessor);

            if ($anchor === null) {
                return ['blocked' => true, 'ready' => false, 'earliest_start' => $earliestStart];
            }

            $ready = $ready && $predecessor->status === 'complete';
            $candidate = $this->applyLag($anchor, $dependency);

            if ($dependency->dependency_type === 'finish_to_start') {
                $candidate = $candidate->addDay();
            }

            $candidate = $dependency->lag_unit === 'calendar_days'
                ? $candidate
                : $this->calendar->nextWorkingDay($candidate);

            if ($candidate->greaterThan($earliestStart)) {
                $earliestStart = $candidate;
            }
        }

        return ['blocked' => false, 'ready' => $ready, 'earliest_start' => $earliestStart];
    }

    private function dependencyAnchor(TimelineTaskDependency $dependency, TimelineTask $predecessor): ?CarbonImmutable
    {
        return match ($dependency->dependency_type) {
            'start_to_start' => $predecessor->starts_on ? CarbonImmutable::parse($predecessor->starts_on)->startOfDay() : null,
            default => $predecessor->completed_on
                ? CarbonImmutable::parse($predecessor->completed_on)->startOfDay()
                : ($predecessor->due_on ? CarbonImmutable::parse($predecessor->due_on)->startOfDay() : null),
        };
    }

    private function applyLag(CarbonImmutable $anchor, TimelineTaskDependency $dependency): CarbonImmutable
    {
        if ((int) $dependency->lag_days <= 0) {
            return $anchor;
        }

        return $dependency->lag_unit === 'calendar_days'
            ? $anchor->addDays((int) $dependency->lag_days)
            : $this->calendar->addWorkingDays($anchor, (int) $dependency->lag_days);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $subcontractors
     * @param  array<int, array<string, int>>  $occupancy
     * @param  Collection<int, TimelineTask>  $tasks
     * @return array{subcontractor_id: int|null, range: DateRange}|null
     */
    private function findAssignment(
        TimelineTask $task,
        CarbonImmutable $earliestStart,
        Collection $subcontractors,
        array $occupancy,
        Collection $tasks,
    ): ?array {
        $candidateStart = $task->uses_calendar_days
            ? CarbonImmutable::parse($earliestStart)->startOfDay()
            : $this->calendar->nextWorkingDay($earliestStart);
        $attempts = 0;

        while ($attempts < self::HORIZON_DAYS) {
            $range = $task->uses_calendar_days
                ? $this->calendar->calendarDateRange($candidateStart, (int) ($task->default_duration_working_days ?: 1))
                : $this->calendar->workingDateRange($candidateStart, (int) ($task->default_duration_working_days ?: 1));

            if ($task->subcontractor_type_id === null) {
                if (! $this->hasSameProjectConflict($task, $range, $tasks, null)) {
                    return ['subcontractor_id' => null, 'range' => $range];
                }

                $candidateStart = $task->uses_calendar_days
                    ? $candidateStart->addDay()
                    : $this->calendar->nextWorkingDay($candidateStart->addDay());
                $attempts++;

                continue;
            }

            $subcontractor = $subcontractors
                ->where('subcontractor_type_id', (int) $task->subcontractor_type_id)
                ->where('scheduling_is_active', true)
                ->filter(fn (array $subcontractor): bool => $this->hasCapacity($occupancy, $subcontractor, $range)
                    && ! $this->hasSameProjectConflict($task, $range, $tasks, (int) $subcontractor['user_id']))
                ->sortByDesc(fn (array $subcontractor): int => (int) $this->scoreBreakdown($task, $range->start, (int) $subcontractor['user_id'])['crew_continuity'])
                ->sortByDesc('reliability_score')
                ->sortByDesc('scheduling_capacity_daily')
                ->first();

            if (is_array($subcontractor)) {
                return ['subcontractor_id' => (int) $subcontractor['user_id'], 'range' => $range];
            }

            $candidateStart = $task->uses_calendar_days
                ? $candidateStart->addDay()
                : $this->calendar->nextWorkingDay($candidateStart->addDay());
            $attempts++;
        }

        return null;
    }

    /**
     * @param  Collection<int, TimelineTask>  $tasks
     */
    private function hasSameProjectConflict(TimelineTask $task, DateRange $range, Collection $tasks, ?int $assignedSubcontractorId): bool
    {
        return $tasks
            ->filter(fn (TimelineTask $other): bool => (int) $other->project_id === (int) $task->project_id
                && (int) $other->id !== (int) $task->id
                && ! in_array($other->status, ['complete', 'cancelled', 'not_scheduled', 'blocked'], true)
                && $other->starts_on !== null
                && $other->due_on !== null
                && $range->overlaps(DateRange::from($other->starts_on, $other->due_on)))
            ->contains(fn (TimelineTask $other): bool => ! $this->canOverlapSameProject($task, $other, $assignedSubcontractorId));
    }

    private function canOverlapSameProject(TimelineTask $task, TimelineTask $other, ?int $assignedSubcontractorId): bool
    {
        if ($assignedSubcontractorId !== null && (int) $other->assigned_subcontractor_id === $assignedSubcontractorId) {
            return true;
        }

        $task->loadMissing('subcontractorType');
        $other->loadMissing('subcontractorType');

        return (bool) $task->subcontractorType?->allows_same_project_overlap
            && (bool) $other->subcontractorType?->allows_same_project_overlap;
    }

    /**
     * @param  array<int, array<string, int>>  $occupancy
     * @param  array<string, mixed>  $subcontractor
     */
    private function hasCapacity(array $occupancy, array $subcontractor, DateRange $range): bool
    {
        $capacity = max(1, (int) $subcontractor['scheduling_capacity_daily']);
        $userId = (int) $subcontractor['user_id'];

        foreach (CarbonPeriod::create($range->start, $range->end) as $date) {
            $key = $date->toDateString();

            if (($occupancy[$userId][$key] ?? 0) >= $capacity) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, int>>  $occupancy
     */
    private function reserveCapacity(array &$occupancy, int $subcontractorId, DateRange $range): void
    {
        foreach (CarbonPeriod::create($range->start, $range->end) as $date) {
            $key = $date->toDateString();
            $occupancy[$subcontractorId][$key] = ($occupancy[$subcontractorId][$key] ?? 0) + 1;
        }
    }

    /**
     * @return array<string, int>
     */
    private function scoreBreakdown(TimelineTask $task, CarbonImmutable $earliestStart, ?int $subcontractorId): array
    {
        $daysWaiting = $task->ready_since
            ? max(0, (int) CarbonImmutable::parse($task->ready_since)->diffInDays(now()))
            : max(0, (int) $earliestStart->diffInDays(now(), false));

        return [
            'job_priority' => max(1, min(4, (int) ($task->priority ?: 2))) * 30,
            'days_waiting' => $daysWaiting * 10,
            'dependency_unlock_value' => $this->dependencyUnlockValue($task) * 25,
            'customer_urgency' => max(0, min(4, (int) $task->customer_urgency)) * 15,
            'crew_continuity' => $this->crewContinuity($task, $subcontractorId) ? 10 : 0,
            'conflict_penalty' => 0,
            'material_risk_penalty' => 0,
            'site_risk_penalty' => 0,
            'inspection_risk_penalty' => 0,
        ];
    }

    private function dependencyUnlockValue(TimelineTask $task): int
    {
        $task->loadMissing('successorDependencies.successor');

        return $task->successorDependencies
            ->filter(fn (TimelineTaskDependency $dependency): bool => ! in_array($dependency->successor?->status, ['complete', 'cancelled'], true))
            ->count();
    }

    private function crewContinuity(TimelineTask $task, ?int $subcontractorId): bool
    {
        if ($subcontractorId === null || $task->project_id === null) {
            return false;
        }

        return TimelineTask::query()
            ->where('project_id', $task->project_id)
            ->where('assigned_subcontractor_id', $subcontractorId)
            ->where('status', 'complete')
            ->whereKeyNot($task->id)
            ->exists();
    }

    /**
     * @param  list<string>  $reasons
     */
    private function markBlocked(TimelineTask $task, array $reasons): void
    {
        $task->forceFill([
            'starts_on' => $task->is_schedule_locked ? $task->starts_on : null,
            'due_on' => $task->is_schedule_locked ? $task->due_on : null,
            'status' => 'blocked',
            'readiness_status' => 'blocked',
            'schedule_score' => 0,
            'score_breakdown' => ['block_reasons' => count($reasons)],
            'last_scheduled_at' => now(),
        ])->save();
    }

    /**
     * @param  Collection<int, TimelineTask>  $tasks
     * @param  Collection<int, array<string, mixed>>  $subcontractors
     * @param  Collection<int, int>  $scheduledTaskIds
     */
    private function markRemaining(ScheduleRun $run, Collection $tasks, Collection $subcontractors, Collection $scheduledTaskIds): void
    {
        $tasks
            ->reject(fn (TimelineTask $task): bool => $scheduledTaskIds->contains($task->id)
                || in_array($task->status, ['complete', 'cancelled'], true)
                || ($task->status === 'in_progress' && $task->actual_start_date !== null)
                || $task->is_schedule_locked
                || $task->is_system)
            ->each(function (TimelineTask $task) use ($run, $subcontractors): void {
                $reasons = $this->blockReasons($task, $subcontractors);

                if ($reasons !== []) {
                    $this->markBlocked($task, $reasons);
                    $this->recordRunItem($run, $task, $reasons, implode(' ', $reasons));

                    return;
                }

                $task->forceFill([
                    'status' => 'not_scheduled',
                    'readiness_status' => 'waiting_dependencies',
                    'schedule_score' => 0,
                    'score_breakdown' => null,
                ])->save();

                $this->recordRunItem($run, $task, ['Waiting on predecessor schedule.'], 'Task is waiting on predecessor work.');
            });
    }

    private function recordRunItem(ScheduleRun $run, TimelineTask $task, array $blockReasons, string $explanation): void
    {
        $run->items()->create([
            'company_id' => $task->company_id,
            'project_id' => $task->project_id,
            'timeline_task_id' => $task->id,
            'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
            'status' => $task->status,
            'readiness_status' => $task->readiness_status,
            'scheduled_start' => $task->starts_on,
            'scheduled_end' => $task->due_on,
            'score' => $task->schedule_score,
            'score_breakdown' => $task->score_breakdown,
            'block_reasons' => $blockReasons,
            'explanation' => $explanation,
        ]);
    }

    /**
     * @param  Collection<int, \App\Models\Project>  $projects
     */
    private function updateProjects(Collection $projects): void
    {
        $projects->each(function ($project): void {
            $tasks = $project->timelineTasks()->get();
            $count = max(1, $tasks->count());
            $complete = $tasks->where('status', 'complete')->count();
            $blocked = $tasks->where('status', 'blocked')->count();
            $nextTask = $tasks->first(fn (TimelineTask $task): bool => in_array($task->status, ['in_progress', 'ready', 'scheduled', 'blocked'], true));

            $project->forceFill([
                'percent_complete' => (int) round(($complete / $count) * 100),
                'health_status' => $blocked > 0 ? 'at_risk' : 'on_track',
                'next_step' => $nextTask?->title,
            ])->save();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function subcontractors(Company $company): Collection
    {
        return DB::table('company_user')
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->join('subcontractor_types', 'subcontractor_types.id', '=', 'company_user.subcontractor_type_id')
            ->where('company_user.company_id', $company->id)
            ->where('company_user.role', Roles::SUBCONTRACTOR)
            ->whereNotNull('company_user.subcontractor_type_id')
            ->where('subcontractor_types.is_active', true)
            ->select([
                'company_user.user_id',
                'company_user.subcontractor_type_id',
                'company_user.scheduling_capacity_daily',
                'company_user.reliability_score',
                'company_user.scheduling_is_active',
                'users.name',
            ])
            ->get()
            ->map(fn (object $row): array => [
                'user_id' => (int) $row->user_id,
                'subcontractor_type_id' => (int) $row->subcontractor_type_id,
                'scheduling_capacity_daily' => max(1, (int) $row->scheduling_capacity_daily),
                'reliability_score' => max(0, (int) $row->reliability_score),
                'scheduling_is_active' => (bool) $row->scheduling_is_active,
                'name' => (string) $row->name,
            ]);
    }

    private function consumesLockedCapacity(TimelineTask $task): bool
    {
        return $task->assigned_subcontractor_id !== null
            && $task->starts_on !== null
            && $task->due_on !== null
            && ! in_array($task->status, TimelineScheduler::CLOSED_STATUSES, true)
            && ($task->is_schedule_locked || ($task->status === 'in_progress' && $task->actual_start_date !== null));
    }

    private function normalizeCompletedTask(TimelineTask $task): void
    {
        $completedOn = $task->completed_on
            ? CarbonImmutable::parse($task->completed_on)->startOfDay()
            : CarbonImmutable::parse($task->due_on ?? now())->startOfDay();
        $startsOn = $task->starts_on
            ? CarbonImmutable::parse($task->starts_on)->startOfDay()
            : null;

        $task->forceFill([
            'completed_on' => $completedOn,
            'due_on' => $completedOn,
            'default_duration_working_days' => $startsOn === null
                ? max(1, (int) ($task->default_duration_working_days ?? 1))
                : $this->calendar->countWorkingDaysInclusive($startsOn, $completedOn),
            'readiness_status' => 'complete',
            'schedule_score' => 0,
            'score_breakdown' => null,
            'last_scheduled_at' => now(),
        ])->save();
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'upcoming', 'rescheduled' => 'scheduled',
            'delayed' => 'blocked',
            'completed' => 'complete',
            default => $status,
        };
    }

    private function statusForRange(DateRange $range, string $readinessStatus): string
    {
        if ($readinessStatus === 'blocked') {
            return 'blocked';
        }

        $today = CarbonImmutable::today();

        if ($readinessStatus === 'ready' && $range->start->lessThanOrEqualTo($today)) {
            return $range->end->greaterThanOrEqualTo($today) ? 'in_progress' : 'scheduled';
        }

        return $readinessStatus === 'ready' ? 'ready' : 'scheduled';
    }

    private function shortDateRange(DateRange $range): string
    {
        if ($range->start->isSameDay($range->end)) {
            return $range->start->format('M j');
        }

        return $range->start->format('M j').' - '.$range->end->format('M j');
    }
}
