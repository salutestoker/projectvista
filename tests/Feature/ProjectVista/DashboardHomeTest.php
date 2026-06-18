<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Company;
use App\Models\User;
use App\Support\ProjectVista\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DashboardHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_dashboard_includes_company_context_without_projects(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create();

        $company->users()->attach($admin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'title' => 'Owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Dashboard')
                ->where('role', 'company_admin')
                ->where('company.slug', $company->slug)
                ->where('primaryProject', null)
                ->has('companies', 1));
    }

    public function test_company_admin_receives_owner_dashboard_home_payload(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Dashboard')
                ->where('role', 'company_admin')
                ->where('home.type', 'owner')
                ->has('home.metrics', 6)
                ->has('home.project_rows', 6)
                ->where('stats.active_projects', 6));
    }

    public function test_company_manager_receives_manager_dashboard_home_payload(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Dashboard')
                ->where('role', 'company_manager')
                ->where('home.type', 'manager')
                ->has('home.metrics', 6)
                ->has('home.project_rows', 6));
    }

    public function test_subcontractor_home_payload_hides_payments_and_messages(): void
    {
        $this->seed();

        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();

        $this->actingAs($subcontractor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Dashboard')
                ->where('role', 'subcontractor')
                ->where('home.type', 'subcontractor')
                ->has('home.project_rows', 6)
                ->where('home.project_rows', fn ($rows) => collect($rows)
                    ->every(fn (array $row) => ! array_key_exists('payment_total', $row)
                        && ! array_key_exists('payment_paid', $row)
                        && ! array_key_exists('messages', $row))));
    }

    public function test_client_receives_customer_dashboard_home_payload(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Dashboard')
                ->where('role', 'client')
                ->where('home.type', 'client')
                ->where('home.project.name', 'Smith Residence')
                ->has('home.updates'));
    }

    public function test_component_library_is_super_admin_only(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'super@projectvista.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();

        $this->actingAs($superAdmin)
            ->get(route('super-admin.components'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ComponentLibrary'));

        $this->actingAs($manager)
            ->get(route('super-admin.components'))
            ->assertForbidden();
    }
}
