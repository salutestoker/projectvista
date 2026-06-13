<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_client_can_view_project_portal(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->get(route('projects.show', $project))
            ->assertOk();
    }

    public function test_company_from_another_tenant_cannot_view_project(): void
    {
        $this->seed();

        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($otherAdmin)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_subcontractor_cannot_access_payments_approvals_or_messages(): void
    {
        $this->seed();

        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($subcontractor)
            ->get(route('projects.payments', $project))
            ->assertForbidden();

        $this->actingAs($subcontractor)
            ->get(route('projects.approvals', $project))
            ->assertForbidden();

        $this->actingAs($subcontractor)
            ->get(route('projects.messages', $project))
            ->assertForbidden();
    }

    public function test_super_admin_can_open_command_center(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'super@projectvista.test')->firstOrFail();

        $this->actingAs($superAdmin)
            ->get(route('super-admin.dashboard'))
            ->assertOk();
    }
}
