<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DocumentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_does_not_receive_internal_documents(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->get(route('projects.documents', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Documents')
                ->where('project.documents', fn ($documents) => collect($documents)
                    ->doesntContain(fn (array $document) => $document['visibility'] === 'internal')));
    }

    public function test_subcontractor_receives_only_visible_documents(): void
    {
        $this->seed();

        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($subcontractor)
            ->get(route('projects.documents', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Documents')
                ->where('project.documents', fn ($documents) => collect($documents)
                    ->every(fn (array $document) => $document['subcontractor_visible'] === true)));
    }
}
