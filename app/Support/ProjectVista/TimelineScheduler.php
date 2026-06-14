<?php

declare(strict_types=1);

namespace App\Support\ProjectVista;

use App\Models\Project;
use App\Models\TimelineTask;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TimelineScheduler
{
    public const OPEN_STATUSES = ['upcoming', 'in_progress', 'blocked', 'needs_approval'];

    public const STATUSES = ['upcoming', 'in_progress', 'blocked', 'needs_approval', 'completed'];

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
                ->whereIn('status', self::OPEN_STATUSES)
                ->orderBy('starts_on')
                ->orderBy('sort_order')
                ->get();
        }

        return $query
            ->where('project_id', $contextProject->id)
            ->when($role === Roles::CLIENT, fn (Builder $tasks) => $tasks->where('client_visible', true))
            ->when($role === Roles::SUBCONTRACTOR, fn (Builder $tasks) => $tasks
                ->where('subcontractor_visible', true)
                ->where('assigned_subcontractor_id', $user->id))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array<string, mixed>>
     */
    public function conflictsFor(Project $project, array $attributes, ?TimelineTask $task = null): array
    {
        $startsOn = $attributes['starts_on'] ?? null;
        $dueOn = $attributes['due_on'] ?? null;
        $assignedSubcontractorId = $attributes['assigned_subcontractor_id'] ?? null;

        if (! $startsOn || ! $dueOn || ! $assignedSubcontractorId) {
            return [];
        }

        $baseQuery = TimelineTask::query()
            ->with(['project', 'assignedSubcontractor', 'subcontractorType'])
            ->where('company_id', $project->company_id)
            ->where('status', '!=', 'completed')
            ->whereNotNull('starts_on')
            ->whereNotNull('due_on')
            ->when($task !== null, fn (Builder $query) => $query->where('id', '!=', $task->id))
            ->where(fn (Builder $query) => $query
                ->whereDate('starts_on', '<=', $dueOn)
                ->whereDate('due_on', '>=', $startsOn));

        $subcontractorConflicts = (clone $baseQuery)
            ->where('assigned_subcontractor_id', $assignedSubcontractorId)
            ->get()
            ->toBase()
            ->map(fn (TimelineTask $conflict): array => [
                'type' => 'subcontractor_double_booked',
                'label' => 'Subcontractor Double-Booked',
                'project_name' => $project->name,
                'conflicting_project_name' => $conflict->project?->name,
                'task_title' => $conflict->title,
                'date_range' => $this->shortDateRange($conflict),
                'subcontractor_name' => $conflict->assignedSubcontractor?->name,
            ]);

        $sameProjectConflicts = (clone $baseQuery)
            ->where('project_id', $project->id)
            ->whereNotNull('assigned_subcontractor_id')
            ->where('assigned_subcontractor_id', '!=', $assignedSubcontractorId)
            ->get()
            ->toBase()
            ->map(fn (TimelineTask $conflict): array => [
                'type' => 'same_day_project_conflict',
                'label' => 'Same-Day Project Conflict',
                'project_name' => $project->name,
                'conflicting_project_name' => $conflict->project?->name,
                'task_title' => $conflict->title,
                'date_range' => $this->shortDateRange($conflict),
                'subcontractor_name' => $conflict->assignedSubcontractor?->name,
            ]);

        return $subcontractorConflicts
            ->merge($sameProjectConflicts)
            ->unique(fn (array $conflict) => implode('|', [
                $conflict['type'],
                $conflict['conflicting_project_name'],
                $conflict['task_title'],
                $conflict['date_range'],
            ]))
            ->values()
            ->all();
    }

    public function taskRow(TimelineTask $task): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name ?? 'Project',
            'project_slug' => $task->project?->slug,
            'project_code' => 'PV-'.str_pad((string) (1000 + (int) $task->project_id), 4, '0', STR_PAD_LEFT),
            'title' => $task->title,
            'phase' => $task->phase,
            'description' => $task->description,
            'sort_order' => $task->sort_order,
            'status' => $task->status,
            'starts_on' => $task->starts_on?->toFormattedDateString(),
            'starts_on_input' => $task->starts_on?->toDateString(),
            'due_on' => $task->due_on?->toFormattedDateString(),
            'due_on_input' => $task->due_on?->toDateString(),
            'completed_on' => $task->completed_on?->toFormattedDateString(),
            'client_visible' => $task->client_visible,
            'subcontractor_visible' => $task->subcontractor_visible,
            'requires_acknowledgement' => $task->requires_acknowledgement,
            'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
            'assigned_subcontractor_name' => $task->assignedSubcontractor?->name,
            'subcontractor_type_id' => $task->subcontractor_type_id,
            'subcontractor_type_name' => $task->subcontractorType?->name,
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
            'completed' => 100,
            'in_progress' => 65,
            'blocked', 'needs_approval' => 35,
            default => 15,
        };
    }
}
