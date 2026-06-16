<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Company;
use App\Models\Project;
use App\Models\ScheduleChangeLog;
use App\Models\SubcontractorType;
use App\Models\TimelineTask;
use App\Models\User;
use App\Services\Scheduling\ScheduleConflictDetector;
use App\Services\Scheduling\ProjectTimelineScheduler;
use App\Support\ProjectVista\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TimelineSchedulingSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_signed_date_generates_default_scheduled_timeline(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create();
        $company->users()->attach($admin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'joined_at' => now(),
        ]);
        $project = Project::factory()->for($company)->create([
            'contract_signed_on' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('projects.update', $project), [
                'customer_name' => $project->client_name,
                'customer_email' => $project->client_email,
                'address_line' => $project->address_line,
                'city' => $project->city,
                'state' => $project->state,
                'postal_code' => $project->postal_code,
                'contract_amount' => $project->contract_amount,
                'contract_signed_on' => '2024-04-18',
            ])
            ->assertRedirect();

        $tasks = $project->refresh()->timelineTasks()->orderBy('sequence_order')->get();

        $this->assertCount(13, $tasks);
        $this->assertSame(range(1, 13), $tasks->pluck('sequence_order')->all());
        $this->assertSame('Contract Signed', $tasks[0]->title);
        $this->assertTrue($tasks[0]->is_system);
        $this->assertSame('complete', $tasks[0]->status);
        $this->assertSame('2024-04-18', $tasks[0]->starts_on->toDateString());
        $this->assertSame('Permit Received', $tasks[1]->title);
        $this->assertSame('2024-04-19', $tasks[1]->starts_on->toDateString());
        $this->assertTrue($tasks->slice(2)->every(
            fn (TimelineTask $task) => $task->starts_on->isAfter($tasks[1]->due_on),
        ));
    }

    public function test_weekend_contract_signed_date_keeps_contract_task_on_weekend_and_starts_next_task_on_working_day(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create();
        $company->users()->attach($admin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'joined_at' => now(),
        ]);
        $project = Project::factory()->for($company)->create([
            'contract_signed_on' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('projects.update', $project), [
                'customer_name' => $project->client_name,
                'customer_email' => $project->client_email,
                'address_line' => $project->address_line,
                'city' => $project->city,
                'state' => $project->state,
                'postal_code' => $project->postal_code,
                'contract_amount' => $project->contract_amount,
                'contract_signed_on' => '2024-04-20',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tasks = $project->refresh()->timelineTasks()->orderBy('sequence_order')->get();

        $this->assertSame('Contract Signed', $tasks[0]->title);
        $this->assertTrue($tasks[0]->starts_on->isSaturday());
        $this->assertSame('2024-04-20', $tasks[0]->starts_on->toDateString());
        $this->assertSame('2024-04-20', $tasks[0]->due_on->toDateString());
        $this->assertSame('Permit Received', $tasks[1]->title);
        $this->assertTrue($tasks[1]->starts_on->isMonday());
        $this->assertSame('2024-04-22', $tasks[1]->starts_on->toDateString());
    }

    public function test_weekend_contract_signed_task_does_not_create_non_working_day_conflict(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create();
        $company->users()->attach($admin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'joined_at' => now(),
        ]);
        $project = Project::factory()->for($company)->create([
            'contract_signed_on' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('projects.update', $project), [
                'customer_name' => $project->client_name,
                'customer_email' => $project->client_email,
                'address_line' => $project->address_line,
                'city' => $project->city,
                'state' => $project->state,
                'postal_code' => $project->postal_code,
                'contract_amount' => $project->contract_amount,
                'contract_signed_on' => '2024-04-21',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $contractTask = $project->refresh()->timelineTasks()
            ->where('title', 'Contract Signed')
            ->firstOrFail();
        $conflicts = app(ScheduleConflictDetector::class)->detectForTask($contractTask);

        $this->assertTrue($contractTask->starts_on->isSunday());
        $this->assertFalse($conflicts->contains(fn ($conflict) => $conflict->type === 'non_working_day'));
    }

    public function test_preview_endpoint_returns_structured_blocking_conflict(): void
    {
        [$company, $admin, $project, $task, $otherTask] = $this->conflictFixture();

        $this->actingAs($admin)
            ->postJson(route('projects.timeline.tasks.preview', [$project, $task]), [
                'status' => 'upcoming',
                'starts_on' => $otherTask->starts_on->toDateString(),
                'due_on' => $otherTask->due_on->toDateString(),
                'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
                'subcontractor_type_id' => $task->subcontractor_type_id,
                'is_job_site_ready' => true,
                'are_materials_ready' => true,
                'is_customer_approval_required' => false,
                'is_customer_approval_received' => false,
            ])
            ->assertOk()
            ->assertJsonPath('can_save', false)
            ->assertJsonPath('requires_override', false)
            ->assertJsonPath('conflicts.0.type', 'subcontractor_double_booked')
            ->assertJsonPath('conflicts.0.severity', 'blocking')
            ->assertJsonPath('conflicts.0.project_conflict', $otherTask->project->name);

        $this->assertSame($company->id, $project->company_id);
    }

    public function test_task_save_reschedules_project_and_logs_change(): void
    {
        [, $admin, $project, $task] = $this->conflictFixture();
        $this->actingAs($admin)
            ->patch(route('projects.timeline.tasks.update', [$project, $task]), [
                'title' => $task->title,
                'description' => $task->description,
                'status' => 'upcoming',
                'duration_days' => 8,
                'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
                'subcontractor_type_id' => $task->subcontractor_type_id,
                'internal_only' => false,
                'requires_acknowledgement' => false,
                'is_job_site_ready' => true,
                'are_materials_ready' => true,
                'is_customer_approval_required' => false,
                'is_customer_approval_received' => false,
                'internal_notes' => null,
                'customer_notes' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(8, $task->default_duration_working_days);
        $this->assertNotNull($task->starts_on);
        $this->assertNotNull($task->due_on);
        $this->assertTrue($task->due_on->greaterThanOrEqualTo($task->starts_on));
        $this->assertTrue(ScheduleChangeLog::query()
            ->where('timeline_task_id', $task->id)
            ->where('blocked_by_conflicts', false)
            ->exists());
    }

    public function test_marking_task_complete_sets_actual_working_day_duration(): void
    {
        [, $admin, $project, $task] = $this->conflictFixture();
        $task->update([
            'starts_on' => '2024-07-01',
            'due_on' => '2024-07-12',
            'default_duration_working_days' => 10,
            'status' => 'in_progress',
            'completed_on' => null,
        ]);

        $this->travelTo('2024-07-10 12:00:00');

        try {
            $this->actingAs($admin)
                ->patch(route('projects.timeline.tasks.update', [$project, $task]), [
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => 'complete',
                    'duration_days' => 10,
                    'assigned_subcontractor_id' => $task->assigned_subcontractor_id,
                    'subcontractor_type_id' => $task->subcontractor_type_id,
                    'internal_only' => false,
                    'requires_acknowledgement' => false,
                    'is_job_site_ready' => true,
                    'are_materials_ready' => true,
                    'is_customer_approval_required' => false,
                    'is_customer_approval_received' => false,
                    'internal_notes' => null,
                    'customer_notes' => null,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        } finally {
            $this->travelBack();
        }

        $task->refresh();

        $this->assertSame('complete', $task->status);
        $this->assertSame('2024-07-10', $task->completed_on->toDateString());
        $this->assertSame('2024-07-10', $task->due_on->toDateString());
        $this->assertSame(8, $task->default_duration_working_days);
    }

    public function test_long_tasks_do_not_report_non_working_day_conflicts(): void
    {
        $task = $this->taskForNonWorkingDayConflict(8, '2024-07-01', '2024-07-10');

        $conflicts = app(ScheduleConflictDetector::class)->detectForProposedChange($task, [
            'starts_on' => '2024-07-01',
            'due_on' => '2024-07-10',
            'status' => 'upcoming',
            'is_job_site_ready' => true,
            'are_materials_ready' => true,
            'is_customer_approval_required' => false,
            'is_customer_approval_received' => false,
        ]);

        $this->assertFalse($conflicts->contains(fn ($conflict) => $conflict->type === 'non_working_day'));
    }

    public function test_short_tasks_still_report_non_working_day_conflicts(): void
    {
        $task = $this->taskForNonWorkingDayConflict(5, '2024-07-01', '2024-07-07');

        $conflicts = app(ScheduleConflictDetector::class)->detectForProposedChange($task, [
            'starts_on' => '2024-07-01',
            'due_on' => '2024-07-07',
            'status' => 'upcoming',
            'is_job_site_ready' => true,
            'are_materials_ready' => true,
            'is_customer_approval_required' => false,
            'is_customer_approval_received' => false,
        ]);

        $this->assertTrue($conflicts->contains(fn ($conflict) => $conflict->type === 'non_working_day'));
    }

    public function test_priority_rescheduler_moves_later_project_conflicts(): void
    {
        [$company, , $project, $task, $otherTask] = $this->conflictFixture();

        app(ProjectTimelineScheduler::class)->rescheduleCompanyProjectsByPriority($company);

        $task->refresh();
        $otherTask->refresh();

        $this->assertTrue($project->contract_signed_on->lessThan($otherTask->project->contract_signed_on));
        $this->assertTrue($task->due_on->lessThan($otherTask->starts_on));
        $this->assertSame(
            range(1, $otherTask->project->timelineTasks()->count()),
            $otherTask->project->timelineTasks()
                ->orderBy('sequence_order')
                ->pluck('sequence_order')
                ->all(),
        );
    }

    public function test_subcontractor_and_customer_timeline_visibility(): void
    {
        $this->seed();

        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $customer = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();

        $this->actingAs($customer)
            ->get(route('projects.timeline', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timeline.can_edit', false)
                ->where('timeline.tasks', fn ($tasks) => collect($tasks)
                    ->every(fn (array $task) => $task['project_id'] === $project->id
                        && $task['assigned_subcontractor_name'] === null)));

        $this->actingAs($subcontractor)
            ->get(route('projects.timeline', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timeline.can_edit', false)
                ->where('timeline.tasks', fn ($tasks) => collect($tasks)
                    ->every(fn (array $task) => $task['project_id'] === $project->id
                        && $task['internal_only'] === false
                        && $task['assigned_subcontractor_id'] === $subcontractor->id)));
    }

    /**
     * @return array{Company, User, Project, TimelineTask, TimelineTask}
     */
    private function conflictFixture(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create();
        $subcontractor = User::factory()->create(['name' => 'Tile Pro Solutions']);
        $type = SubcontractorType::query()->create([
            'company_id' => $company->id,
            'name' => 'Tile & Stone',
            'slug' => 'tile-stone',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $company->users()->attach($admin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'joined_at' => now(),
        ]);
        $company->users()->attach($subcontractor->id, [
            'role' => Roles::SUBCONTRACTOR,
            'title' => 'Tile Pro Solutions',
            'subcontractor_type_id' => $type->id,
            'joined_at' => now(),
        ]);
        $project = Project::factory()->for($company)->create([
            'name' => 'Smith Residence',
            'slug' => 'smith-residence-test',
            'contract_signed_on' => '2024-04-18',
        ]);
        $otherProject = Project::factory()->for($company)->create([
            'name' => 'Miller Residence',
            'slug' => 'miller-residence-test',
            'contract_signed_on' => '2024-04-19',
        ]);

        DB::table('project_user')->insert([
            [
                'project_id' => $project->id,
                'user_id' => $subcontractor->id,
                'role' => Roles::SUBCONTRACTOR,
                'assigned_scope' => 'Tile',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'project_id' => $otherProject->id,
                'user_id' => $subcontractor->id,
                'role' => Roles::SUBCONTRACTOR,
                'assigned_scope' => 'Tile',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(ProjectTimelineScheduler::class)->createDefaultTimeline($project);
        app(ProjectTimelineScheduler::class)->createDefaultTimeline($otherProject);

        $task = TimelineTask::query()
            ->where('project_id', $project->id)
            ->where('title', 'Tile')
            ->firstOrFail();
        $otherTask = TimelineTask::query()
            ->where('project_id', $otherProject->id)
            ->where('title', 'Tile')
            ->firstOrFail();

        $task->update([
            'assigned_subcontractor_id' => $subcontractor->id,
            'subcontractor_type_id' => $type->id,
            'starts_on' => '2024-07-01',
            'due_on' => '2024-07-05',
            'status' => 'upcoming',
        ]);
        $otherTask->update([
            'assigned_subcontractor_id' => $subcontractor->id,
            'subcontractor_type_id' => $type->id,
            'starts_on' => '2024-07-08',
            'due_on' => '2024-07-10',
            'status' => 'upcoming',
        ]);

        return [$company, $admin, $project, $task->refresh(), $otherTask->refresh()];
    }

    private function taskForNonWorkingDayConflict(int $durationDays, string $startsOn, string $dueOn): TimelineTask
    {
        $company = Company::factory()->create();
        $project = Project::factory()->for($company)->create([
            'contract_signed_on' => '2024-06-28',
        ]);

        return TimelineTask::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'title' => 'Standalone Task',
            'description' => 'Task used for non-working day conflict coverage.',
            'sort_order' => 1,
            'sequence_order' => 1,
            'default_duration_working_days' => $durationDays,
            'status' => 'upcoming',
            'starts_on' => $startsOn,
            'due_on' => $dueOn,
            'internal_only' => false,
            'is_system' => false,
            'requires_acknowledgement' => false,
            'is_job_site_ready' => true,
            'are_materials_ready' => true,
            'is_customer_approval_required' => false,
            'is_customer_approval_received' => false,
        ]);
    }
}
