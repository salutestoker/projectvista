<?php

declare(strict_types=1);

namespace App\Support\ProjectVista;

use App\Models\Project;
use App\Models\TimelineTask;
use App\Models\User;
use App\Services\Scheduling\ScheduleConflict;
use App\Services\Scheduling\ScheduleConflictDetector;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TimelineScheduler
{
    public const CLOSED_STATUSES = ['complete'];

    public const OPEN_STATUSES = [
        'not_scheduled',
        'upcoming',
        'ready',
        'in_progress',
        'blocked',
        'needs_approval',
        'delayed',
        'rescheduled',
    ];

    public const STATUSES = [
        'not_scheduled',
        'upcoming',
        'ready',
        'in_progress',
        'blocked',
        'needs_approval',
        'delayed',
        'rescheduled',
        'complete',
    ];

    public function __construct(private readonly ScheduleConflictDetector $conflictDetector) {}

    public function editableProjectIds(Project $contextProject, User $user): Collection
    {
        if ($user->isSuperAdmin() || $user->companyRole($contextProject->company_id) === Roles::COMPANY_ADMIN) {
            return Project::query()
                ->where('company_id', $contextProject->company_id)
                ->pluck('id');
        }

        if ($user->companyRole($contextProject->company_id) === Roles::COMPANY_MANAGER) {
            $assignedProjectIds = DB::table('project_user')
                ->where('user_id', $user->id)
                ->where('role', Roles::COMPANY_MANAGER)
                ->pluck('project_id');

            return Project::query()
                ->where('company_id', $contextProject->company_id)
                ->where(fn (Builder $query) => $query
                    ->where('manager_id', $user->id)
                    ->orWhereIn('id', $assignedProjectIds))
                ->pluck('id');
        }

        return collect();
    }

    public function timelineTasks(Project $contextProject, User $user): Collection
    {
        $role = $this->roleFor($contextProject, $user);
        $query = TimelineTask::query()
            ->with(['project.manager', 'assignedSubcontractor', 'subcontractorType'])
            ->where('company_id', $contextProject->company_id);

        if (in_array($role, [Roles::COMPANY_ADMIN, Roles::COMPANY_MANAGER, 'super_admin'], true)) {
            $projectIds = $this->editableProjectIds($contextProject, $user);

            return $query
                ->whereIn('project_id', $projectIds)
                ->orderBy('starts_on')
                ->orderByRaw('COALESCE(sequence_order, sort_order)')
                ->get();
        }

        return $query
            ->where('project_id', $contextProject->id)
            ->when($role === Roles::CLIENT, fn (Builder $tasks) => $tasks->where('internal_only', false))
            ->when($role === Roles::SUBCONTRACTOR, fn (Builder $tasks) => $tasks
                ->where('assigned_subcontractor_id', $user->id))
            ->orderByRaw('COALESCE(sequence_order, sort_order)')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array<string, mixed>>
     */
    public function conflictsFor(Project $project, array $attributes, ?TimelineTask $task = null): array
    {
        $candidate = $task;

        if ($candidate === null) {
            $candidate = new TimelineTask([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'title' => (string) ($attributes['title'] ?? 'Timeline Task'),
                'status' => (string) ($attributes['status'] ?? 'upcoming'),
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'sequence_order' => $attributes['sequence_order'] ?? null,
            ]);
            $candidate->setRelation('project', $project);
        }

        return $this->conflictDetector
            ->detectForProposedChange($candidate, $attributes)
            ->map(fn (ScheduleConflict $conflict) => $conflict->toArray())
            ->values()
            ->all();
    }

    public function taskRow(TimelineTask $task, ?string $role = null): array
    {
        $client = $role === Roles::CLIENT;

        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name ?? 'Project',
            'project_slug' => $task->project?->slug,
            'project_code' => 'PV-'.str_pad((string) (1000 + (int) $task->project_id), 4, '0', STR_PAD_LEFT),
            'title' => $task->title,
            'description' => $client ? ($task->customer_notes ?: $task->description) : $task->description,
            'sort_order' => $task->sort_order,
            'sequence_order' => $task->sequence_order,
            'default_duration_working_days' => $task->default_duration_working_days,
            'is_system' => $task->is_system,
            'status' => $task->status,
            'status_label' => $client ? $this->customerStatusLabel($task->status) : str($task->status)->headline()->toString(),
            'starts_on' => $task->starts_on?->toFormattedDateString(),
            'starts_on_input' => $task->starts_on?->toDateString(),
            'due_on' => $task->due_on?->toFormattedDateString(),
            'due_on_input' => $task->due_on?->toDateString(),
            'completed_on' => $task->completed_on?->toFormattedDateString(),
            'actual_start_date' => $task->actual_start_date?->toFormattedDateString(),
            'actual_end_date' => $task->actual_end_date?->toFormattedDateString(),
            'internal_only' => $task->internal_only,
            'requires_acknowledgement' => $task->requires_acknowledgement,
            'is_job_site_ready' => $task->is_job_site_ready,
            'are_materials_ready' => $task->are_materials_ready,
            'is_customer_approval_required' => $task->is_customer_approval_required,
            'is_customer_approval_received' => $task->is_customer_approval_received,
            'internal_notes' => $client ? null : $task->internal_notes,
            'customer_notes' => $task->customer_notes,
            'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
            'assigned_subcontractor_name' => $client ? null : $task->assignedSubcontractor?->name,
            'subcontractor_type_id' => $task->subcontractor_type_id,
            'subcontractor_type_name' => $client ? null : $task->subcontractorType?->name,
            'progress' => $this->progressFor($task),
            'date_range' => $this->shortDateRange($task),
        ];
    }

    public function roleFor(Project $project, User $user): string
    {
        if ($user->isSuperAdmin()) {
            return 'super_admin';
        }

        return $user->projectRole($project)
            ?? $user->companyRole($project->company_id)
            ?? 'viewer';
    }

    private function shortDateRange(TimelineTask $task): string
    {
        if ($task->starts_on === null && $task->due_on === null) {
            return 'TBD';
        }

        if ($task->starts_on?->isSameDay($task->due_on)) {
            return $task->starts_on->format('M j');
        }

        return collect([$task->starts_on, $task->due_on])
            ->filter()
            ->map(fn (CarbonInterface $date) => $date->format('M j'))
            ->join(' – ');
    }

    private function progressFor(TimelineTask $task): int
    {
        return match ($task->status) {
            'complete' => 100,
            'in_progress' => 65,
            'ready' => 45,
            'blocked', 'needs_approval' => 35,
            'delayed', 'rescheduled' => 25,
            'not_scheduled' => 5,
            default => 15,
        };
    }

    private function customerStatusLabel(string $status): string
    {
        return match ($status) {
            'not_scheduled', 'upcoming' => 'Upcoming',
            'ready' => 'Ready',
            'in_progress' => 'In Progress',
            'complete' => 'Complete',
            'blocked' => 'Waiting',
            'needs_approval' => 'Needs Review',
            'delayed' => 'Adjusted',
            'rescheduled' => 'Rescheduled',
            default => str($status)->headline()->toString(),
        };
    }
}
