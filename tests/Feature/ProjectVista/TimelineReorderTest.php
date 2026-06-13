<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Project;
use App\Models\TimelineTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
