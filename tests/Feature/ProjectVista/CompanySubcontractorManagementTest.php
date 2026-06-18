<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Company;
use App\Models\SubcontractorType;
use App\Models\User;
use App\Support\ProjectVista\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanySubcontractorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_subcontractors_page_lists_company_subcontractors_only(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('companies.subcontractors.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/CompanySubcontractors')
                ->where('settingsNav.active', 'subcontractors')
                ->where('permissions.can_manage_subcontractors', true)
                ->where('subcontractors', fn ($rows) => collect($rows)
                    ->contains(fn (array $row) => $row['email'] === 'sub@omnipools.test')
                    && ! collect($rows)->contains(fn (array $row) => $row['email'] === 'admin@omnipools.test')));
    }

    public function test_company_admin_and_manager_can_access_subcontractor_types_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('companies.subcontractor-types.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/CompanySubcontractorTypes')
                ->where('settingsNav.active', 'subcontractor_types')
                ->where('permissions.can_manage_subcontractor_types', true)
                ->where('subcontractor_types.0.allows_same_project_overlap', false));

        $this->actingAs($manager)
            ->get(route('companies.subcontractor-types.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/CompanySubcontractorTypes')
                ->where('role', 'company_manager')
                ->where('permissions.can_manage_subcontractor_types', true));
    }

    public function test_company_manager_can_create_update_and_archive_subcontractor_type(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('companies.subcontractor-types.store', $company), [
                'name' => 'Plaster',
                'slug' => 'pool-plaster',
                'sort_order' => 120,
                'is_active' => true,
                'allows_same_project_overlap' => true,
            ])
            ->assertRedirect();

        $type = SubcontractorType::query()
            ->where('company_id', $company->id)
            ->where('slug', 'pool-plaster')
            ->firstOrFail();

        $this->assertTrue($type->allows_same_project_overlap);

        $this->actingAs($manager)
            ->patch(route('companies.subcontractor-types.update', [$company, $type]), [
                'name' => 'Interior Plaster',
                'slug' => 'interior-plaster',
                'sort_order' => 125,
                'is_active' => true,
                'allows_same_project_overlap' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subcontractor_types', [
            'id' => $type->id,
            'company_id' => $company->id,
            'name' => 'Interior Plaster',
            'slug' => 'interior-plaster',
            'sort_order' => 125,
            'is_active' => true,
            'allows_same_project_overlap' => false,
        ]);

        $this->actingAs($manager)
            ->delete(route('companies.subcontractor-types.destroy', [$company, $type->refresh()]))
            ->assertRedirect();

        $this->assertDatabaseHas('subcontractor_types', [
            'id' => $type->id,
            'is_active' => false,
        ]);
    }

    public function test_subcontractor_type_slugs_are_unique_per_company_and_cross_company_updates_are_rejected(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $otherCompany = Company::query()->whereKeyNot($company->id)->firstOrFail();
        $existingType = SubcontractorType::query()
            ->where('company_id', $company->id)
            ->firstOrFail();
        $otherCompanyType = SubcontractorType::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Specialty',
            'slug' => 'other-company-specialty',
            'sort_order' => 999,
            'is_active' => true,
            'allows_same_project_overlap' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('companies.subcontractor-types.store', $company), [
                'name' => 'Duplicate Type',
                'slug' => $existingType->slug,
                'sort_order' => 999,
                'is_active' => true,
                'allows_same_project_overlap' => false,
            ])
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post(route('companies.subcontractor-types.store', $company), [
                'name' => 'Scoped Duplicate Type',
                'slug' => $otherCompanyType->slug,
                'sort_order' => 1000,
                'is_active' => true,
                'allows_same_project_overlap' => false,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('companies.subcontractor-types.update', [$company, $otherCompanyType]), [
                'name' => 'Invalid Cross Company Type',
                'slug' => 'invalid-cross-company-type',
                'sort_order' => 1,
                'is_active' => true,
                'allows_same_project_overlap' => true,
            ])
            ->assertNotFound();
    }

    public function test_inactive_subcontractor_types_are_excluded_from_active_settings_payloads(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $inactiveType = SubcontractorType::query()->create([
            'company_id' => $company->id,
            'name' => 'Archived Specialty',
            'slug' => 'archived-specialty',
            'sort_order' => 999,
            'is_active' => false,
            'allows_same_project_overlap' => true,
        ]);

        foreach ([
            route('companies.admin', $company),
            route('companies.timeline-templates.index', $company),
            route('companies.subcontractors.index', $company),
        ] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('subcontractor_types', fn ($types) => ! collect($types)
                        ->contains(fn (array $type) => $type['id'] === $inactiveType->id)));
        }
    }

    public function test_company_admin_can_quick_edit_subcontractor_scheduling_fields(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $type = SubcontractorType::query()
            ->where('company_id', $company->id)
            ->where('slug', 'plumbing')
            ->firstOrFail();
        $scheduleRunsBefore = $company->scheduleRuns()->count();

        $this->actingAs($admin)
            ->patch(route('companies.subcontractors.scheduling.update', [$company, $subcontractor]), [
                'title' => 'Updated Trade Partner',
                'subcontractor_type_id' => $type->id,
                'scheduling_capacity_daily' => 3,
                'reliability_score' => 92,
                'scheduling_is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $subcontractor->id,
            'title' => 'Updated Trade Partner',
            'subcontractor_type_id' => $type->id,
            'scheduling_capacity_daily' => 3,
            'reliability_score' => 92,
            'scheduling_is_active' => false,
        ]);
        $this->assertGreaterThan($scheduleRunsBefore, $company->scheduleRuns()->count());
    }

    public function test_company_admin_can_add_multiple_subcontractors_from_subcontractors_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $type = SubcontractorType::query()
            ->where('company_id', $company->id)
            ->where('slug', 'plumbing')
            ->firstOrFail();
        $existingUser = User::factory()->create([
            'email' => 'existing.trade@example.test',
            'name' => 'Existing Trade Identity',
        ]);
        $scheduleRunsBefore = $company->scheduleRuns()->count();

        $this->actingAs($admin)
            ->post(route('companies.subcontractors.store', $company), [
                'subcontractors' => [
                    [
                        'name' => 'New Excavation Partner',
                        'email' => 'new.excavation@example.test',
                        'title' => 'Excavation Partner',
                        'subcontractor_type_id' => $type->id,
                        'scheduling_capacity_daily' => 2,
                        'reliability_score' => 88,
                        'scheduling_is_active' => true,
                    ],
                    [
                        'name' => 'Existing Trade Display',
                        'email' => 'existing.trade@example.test',
                        'title' => 'Existing Trade Partner',
                        'subcontractor_type_id' => null,
                        'scheduling_capacity_daily' => 1,
                        'reliability_score' => 91,
                        'scheduling_is_active' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $newUser = User::query()->where('email', 'new.excavation@example.test')->firstOrFail();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $newUser->id,
            'role' => Roles::SUBCONTRACTOR,
            'title' => 'Excavation Partner',
            'subcontractor_type_id' => $type->id,
            'scheduling_capacity_daily' => 2,
            'reliability_score' => 88,
            'scheduling_is_active' => true,
        ]);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $existingUser->id,
            'role' => Roles::SUBCONTRACTOR,
            'title' => 'Existing Trade Partner',
            'subcontractor_type_id' => null,
            'scheduling_capacity_daily' => 1,
            'reliability_score' => 91,
            'scheduling_is_active' => false,
        ]);
        $this->assertSame('Existing Trade Identity', $existingUser->refresh()->name);
        $this->assertGreaterThan($scheduleRunsBefore, $company->scheduleRuns()->count());
    }

    public function test_add_subcontractors_rejects_duplicate_or_already_attached_email(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('companies.subcontractors.store', $company), [
                'subcontractors' => [
                    [
                        'name' => 'Duplicate One',
                        'email' => 'duplicate@example.test',
                        'title' => null,
                        'subcontractor_type_id' => null,
                        'scheduling_capacity_daily' => 1,
                        'reliability_score' => 80,
                        'scheduling_is_active' => true,
                    ],
                    [
                        'name' => 'Duplicate Two',
                        'email' => 'DUPLICATE@example.test',
                        'title' => null,
                        'subcontractor_type_id' => null,
                        'scheduling_capacity_daily' => 1,
                        'reliability_score' => 80,
                        'scheduling_is_active' => true,
                    ],
                    [
                        'name' => 'Already Attached',
                        'email' => 'sub@omnipools.test',
                        'title' => null,
                        'subcontractor_type_id' => null,
                        'scheduling_capacity_daily' => 1,
                        'reliability_score' => 80,
                        'scheduling_is_active' => true,
                    ],
                ],
            ])
            ->assertSessionHasErrors([
                'subcontractors.0.email',
                'subcontractors.1.email',
                'subcontractors.2.email',
            ]);
    }

    public function test_subcontractor_quick_edit_rejects_cross_company_trade_type(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $otherType = SubcontractorType::query()
            ->where('company_id', '!=', $company->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('companies.subcontractors.scheduling.update', [$company, $subcontractor]), [
                'title' => 'Invalid Trade Partner',
                'subcontractor_type_id' => $otherType->id,
                'scheduling_capacity_daily' => 2,
                'reliability_score' => 80,
                'scheduling_is_active' => true,
            ])
            ->assertSessionHasErrors('subcontractor_type_id');
    }

    public function test_company_manager_can_view_but_not_update_subcontractor_settings(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('companies.subcontractors.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/CompanySubcontractors')
                ->where('permissions.can_manage_subcontractors', false));

        $this->actingAs($manager)
            ->patch(route('companies.subcontractors.scheduling.update', [$company, $subcontractor]), [
                'title' => 'Blocked Update',
                'subcontractor_type_id' => null,
                'scheduling_capacity_daily' => 2,
                'reliability_score' => 80,
                'scheduling_is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_company_manager_cannot_add_subcontractors(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('companies.subcontractors.store', $company), [
                'subcontractors' => [
                    [
                        'name' => 'Blocked Partner',
                        'email' => 'blocked.partner@example.test',
                        'title' => null,
                        'subcontractor_type_id' => null,
                        'scheduling_capacity_daily' => 1,
                        'reliability_score' => 80,
                        'scheduling_is_active' => true,
                    ],
                ],
            ])
            ->assertForbidden();
    }
}
