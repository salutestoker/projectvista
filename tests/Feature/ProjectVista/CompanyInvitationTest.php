<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\SubcontractorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_page_includes_default_timeline_template(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('companies.admin', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/CompanyAdmin')
                ->where('role', 'company_admin')
                ->where('permissions.can_manage_users', true)
                ->where('permissions.can_manage_templates', true)
                ->where('timeline_templates.0.name', 'Luxury Pool Build')
                ->has('timeline_templates.0.tasks', 13));
    }

    public function test_company_manager_can_view_company_admin_page_without_user_management(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('companies.admin', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/CompanyAdmin')
                ->where('role', 'company_manager')
                ->where('permissions.can_manage_users', false)
                ->where('permissions.can_manage_templates', true)
                ->has('timeline_templates.0.tasks', 13)
                ->has('projects', 6));
    }

    public function test_company_admin_can_create_invitation(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('companies.invitations.store', $company), [
                'email' => 'invited@example.com',
                'role' => 'client',
            ])
            ->assertRedirect();

        $this->assertTrue(Invitation::query()->where('email', 'invited@example.com')->exists());
    }

    public function test_client_cannot_create_company_invitation(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($client)
            ->post(route('companies.invitations.store', $company), [
                'email' => 'blocked@example.com',
                'role' => 'client',
            ])
            ->assertForbidden();
    }

    public function test_subcontractor_invitation_requires_and_stores_type(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $type = SubcontractorType::query()->where('company_id', $company->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('companies.invitations.store', $company), [
                'email' => 'trade-without-type@example.com',
                'role' => 'subcontractor',
            ])
            ->assertSessionHasErrors('subcontractor_type_id');

        $this->actingAs($admin)
            ->post(route('companies.invitations.store', $company), [
                'email' => 'tile-invite@example.com',
                'role' => 'subcontractor',
                'subcontractor_type_id' => $type->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'email' => 'tile-invite@example.com',
            'role' => 'subcontractor',
            'subcontractor_type_id' => $type->id,
        ]);
    }
}
