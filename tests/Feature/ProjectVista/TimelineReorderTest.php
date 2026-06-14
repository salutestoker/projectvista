<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Project;
use App\Models\SubcontractorType;
use App\Models\TimelineTask;
use App\Models\User;
use App\Support\ProjectVista\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TimelineReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_reorder_project_timeline(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $tasks = $project->timelineTasks()->take(2)->get();

        $this->actingAs($manager)
            ->patch(route('projects.timeline.reorder', $project), [
                'tasks' => [
                    ['id' => $tasks[0]->id, 'sort_order' => 2],
                    ['id' => $tasks[1]->id, 'sort_order' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, TimelineTask::query()->findOrFail($tasks[0]->id)->sort_order);
        $this->assertSame(1, TimelineTask::query()->findOrFail($tasks[1]->id)->sort_order);
    }

    public function test_client_cannot_reorder_project_timeline(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $task = $project->timelineTasks()->firstOrFail();

        $this->actingAs($client)
            ->patch(route('projects.timeline.reorder', $project), [
                'tasks' => [
                    ['id' => $task->id, 'sort_order' => 1],
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_timeline_payload_includes_company_open_tasks_only(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('projects.timeline', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Timeline')
                ->where('timeline.can_edit', true)
                ->where('timeline.metrics.sub_types', 18)
                ->where('timeline.tasks', fn ($tasks) => collect($tasks)
                    ->every(fn (array $task) => $task['status'] !== 'completed')
                    && collect($tasks)
                        ->contains(fn (array $task) => $task['project_name'] === 'Johnson Residence')));
    }

    public function test_manager_timeline_payload_is_limited_to_managed_or_assigned_projects(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = $manager->companies()->where('slug', 'omni-pool-builders')->firstOrFail();
        $contextProject = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $hiddenProject = Project::query()->create([
            'company_id' => $company->id,
            'manager_id' => null,
            'name' => 'Hidden Manager Project',
            'slug' => 'hidden-manager-project',
            'address_line' => '1 Hidden Way',
            'city' => 'Scottsdale',
            'state' => 'AZ',
            'project_type' => 'Pool',
            'status' => 'active',
            'phase' => 'Hidden Task',
        ]);
        TimelineTask::query()->create([
            'company_id' => $company->id,
            'project_id' => $hiddenProject->id,
            'title' => 'Hidden Schedule',
            'phase' => 'Hidden',
            'status' => 'in_progress',
            'sort_order' => 1,
            'starts_on' => now(),
            'due_on' => now()->addDays(2),
        ]);

        $this->actingAs($manager)
            ->get(route('projects.timeline', $contextProject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('timeline.can_edit', true)
                ->where('timeline.tasks', fn ($tasks) => collect($tasks)
                    ->doesntContain(fn (array $task) => $task['project_name'] === 'Hidden Manager Project')));
    }

    public function test_client_and_subcontractor_timeline_payloads_are_project_scoped_and_read_only(): void
    {
        $this->seed();

        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();

        $this->actingAs($client)
            ->get(route('projects.timeline', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('timeline.can_edit', false)
                ->where('timeline.tasks', fn ($tasks) => collect($tasks)
                    ->every(fn (array $task) => $task['project_id'] === $project->id)));

        $this->actingAs($subcontractor)
            ->get(route('projects.timeline', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('timeline.can_edit', false)
                ->where('timeline.tasks', fn ($tasks) => collect($tasks)
                    ->every(fn (array $task) => $task['project_id'] === $project->id && $task['subcontractor_visible'] === true)));
    }

    public function test_task_update_requires_conflict_acknowledgement_then_saves(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $task = TimelineTask::query()
            ->where('project_id', $project->id)
            ->whereNotNull('assigned_subcontractor_id')
            ->where('status', '!=', 'completed')
            ->firstOrFail();
        $conflictingTask = TimelineTask::query()
            ->where('project_id', '!=', $project->id)
            ->where('assigned_subcontractor_id', $task->assigned_subcontractor_id)
            ->where('status', '!=', 'completed')
            ->firstOrFail();
        $payload = $this->taskPayload($task, [
            'starts_on' => $conflictingTask->starts_on->toDateString(),
            'due_on' => $conflictingTask->due_on->toDateString(),
            'status' => 'upcoming',
        ]);

        $this->actingAs($admin)
            ->patch(route('projects.timeline.tasks.update', [$project, $task]), $payload)
            ->assertSessionHasErrors('conflicts')
            ->assertSessionHas('timeline_conflicts');

        $this->assertNotSame('upcoming', $task->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('projects.timeline.tasks.update', [$project, $task]), [
                ...$payload,
                'acknowledge_conflicts' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('timeline_conflicts');

        $this->assertSame('upcoming', $task->refresh()->status);
        $this->assertSame($conflictingTask->starts_on->toDateString(), $task->starts_on->toDateString());
    }

    public function test_same_project_overlapping_subcontractors_create_conflict(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $type = SubcontractorType::query()->where('company_id', $project->company_id)->firstOrFail();
        $otherSubcontractor = User::query()->create([
            'name' => 'Conflict Trade',
            'email' => 'conflict-trade@example.test',
            'password' => bcrypt('password'),
        ]);
        DB::table('company_user')->insert([
            'company_id' => $project->company_id,
            'user_id' => $otherSubcontractor->id,
            'role' => Roles::SUBCONTRACTOR,
            'title' => 'Conflict Trade',
            'subcontractor_type_id' => $type->id,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $existingTask = TimelineTask::query()
            ->where('project_id', $project->id)
            ->whereNotNull('assigned_subcontractor_id')
            ->where('status', '!=', 'completed')
            ->firstOrFail();
        $secondTask = TimelineTask::query()->create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'title' => 'Overlapping Trade',
            'phase' => 'Construction',
            'status' => 'upcoming',
            'sort_order' => 99,
            'starts_on' => $existingTask->starts_on,
            'due_on' => $existingTask->due_on,
            'assigned_subcontractor_id' => $otherSubcontractor->id,
            'subcontractor_type_id' => $type->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('projects.timeline.tasks.update', [$project, $existingTask]), $this->taskPayload($existingTask))
            ->assertSessionHasErrors('conflicts')
            ->assertSessionHas('timeline_conflicts', fn (array $conflicts) => collect($conflicts)
                ->contains(fn (array $conflict) => $conflict['type'] === 'same_day_project_conflict'));

        $this->assertDatabaseHas('timeline_tasks', ['id' => $secondTask->id]);
    }

    public function test_admin_can_create_timeline_task_and_auto_assign_subcontractor_to_project(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $type = SubcontractorType::query()->where('company_id', $project->company_id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('projects.timeline.tasks.store', $project), [
                'project_id' => $project->id,
                'title' => 'New Schedule Item',
                'phase' => 'Construction',
                'status' => 'upcoming',
                'starts_on' => now()->addDays(30)->toDateString(),
                'due_on' => now()->addDays(32)->toDateString(),
                'assigned_subcontractor_id' => $subcontractor->id,
                'subcontractor_type_id' => $type->id,
                'client_visible' => true,
                'subcontractor_visible' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('timeline_tasks', [
            'project_id' => $project->id,
            'title' => 'New Schedule Item',
            'assigned_subcontractor_id' => $subcontractor->id,
        ]);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $subcontractor->id,
            'role' => Roles::SUBCONTRACTOR,
        ]);
    }

    public function test_cross_tenant_user_cannot_update_timeline_task(): void
    {
        $this->seed();

        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $task = $project->timelineTasks()->where('status', '!=', 'completed')->firstOrFail();

        $this->actingAs($otherAdmin)
            ->patch(route('projects.timeline.tasks.update', [$project, $task]), $this->taskPayload($task))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function taskPayload(TimelineTask $task, array $overrides = []): array
    {
        return [
            'title' => $task->title,
            'phase' => $task->phase,
            'description' => $task->description,
            'status' => $task->status,
            'starts_on' => $task->starts_on?->toDateString(),
            'due_on' => $task->due_on?->toDateString(),
            'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
            'subcontractor_type_id' => $task->subcontractor_type_id,
            'client_visible' => $task->client_visible,
            'subcontractor_visible' => $task->subcontractor_visible,
            'requires_acknowledgement' => $task->requires_acknowledgement,
            ...$overrides,
        ];
    }
}
