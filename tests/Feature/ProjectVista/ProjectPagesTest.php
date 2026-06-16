<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\MediaAsset;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\TimelineTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProjectPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_company_project_index(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectsIndex')
                ->where('role', 'company_admin')
                ->has('metrics', 5)
                ->has('rows', 6)
                ->where('rows', fn ($rows) => collect($rows)
                    ->contains(fn (array $row) => $row['slug'] === 'smith-residence')
                    && ! collect($rows)->contains(fn (array $row) => $row['slug'] === 'canyon-courtyard')));
    }

    public function test_company_manager_project_index_is_limited_to_assigned_or_managed_projects(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager.caleb@aurelia.test')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectsIndex')
                ->where('role', 'company_manager')
                ->has('rows', 3)
                ->where('rows', fn ($rows) => collect($rows)
                    ->contains(fn (array $row) => $row['slug'] === 'silverleaf-retreat')
                    && ! collect($rows)->contains(fn (array $row) => $row['slug'] === 'camelback-courtyard')));
    }

    public function test_internal_users_can_view_project_create_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectCreate')
                ->where('role', 'company_admin')
                ->has('companies.0.timeline_templates'));

        $this->actingAs($manager)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectCreate')
                ->where('role', 'company_manager')
                ->has('companies.0.managers')
                ->has('companies.0.subcontractors'));
    }

    public function test_client_and_subcontractor_cannot_view_project_create_page(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();

        $this->actingAs($client)
            ->get(route('projects.create'))
            ->assertForbidden();

        $this->actingAs($subcontractor)
            ->get(route('projects.create'))
            ->assertForbidden();
    }

    public function test_company_admin_can_create_project_from_template_and_invite_unknown_client(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $template = TimelineTemplate::query()
            ->whereHas('company', fn ($query) => $query->where('slug', 'omni-pool-builders'))
            ->withCount('taskTemplates')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                ...$this->projectCreatePayload($template, $manager->id),
                'name' => 'High Desert Escape',
                'client_name' => 'Taylor Newhome',
                'client_email' => 'taylor.newhome@example.com',
                'subcontractor_ids' => [$subcontractor->id],
                'contract_signed_on' => '2026-01-10',
            ])
            ->assertRedirect();

        $project = Project::query()
            ->where('company_id', $template->company_id)
            ->where('slug', 'high-desert-escape')
            ->firstOrFail();

        $this->assertSame($manager->id, $project->manager_id);
        $this->assertSame('Taylor Newhome', $project->client_name);
        $this->assertSame('taylor.newhome@example.com', $project->client_email);
        $this->assertTrue($project->users()->whereKey($manager->id)->wherePivot('role', 'company_manager')->exists());
        $this->assertTrue($project->users()->whereKey($subcontractor->id)->wherePivot('role', 'subcontractor')->exists());
        $this->assertSame($template->task_templates_count, $project->timelineTasks()->count());
        $this->assertTrue($project->timelineTasks()->whereNotNull('starts_on')->exists());
        $contractTask = $project->timelineTasks()->orderBy('sequence_order')->firstOrFail();
        $this->assertSame('Contract Signed', $contractTask->title);
        $this->assertTrue($contractTask->is_system);
        $this->assertSame('complete', $contractTask->status);
        $this->assertSame('2026-01-10', $contractTask->starts_on->toDateString());
        $this->assertDatabaseHas('invitations', [
            'company_id' => $template->company_id,
            'project_id' => $project->id,
            'email' => 'taylor.newhome@example.com',
            'recipient_name' => 'Taylor Newhome',
            'role' => 'client',
            'status' => 'pending',
        ]);
    }

    public function test_company_manager_creating_project_without_manager_field_is_assigned_to_project(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $template = TimelineTemplate::query()
            ->whereHas('company', fn ($query) => $query->where('slug', 'omni-pool-builders'))
            ->firstOrFail();
        $payload = [
            ...$this->projectCreatePayload($template, $manager->id),
            'name' => 'Manager Created Residence',
            'client_email' => 'manager-created@example.com',
        ];
        unset($payload['manager_id']);

        $this->actingAs($manager)
            ->post(route('projects.store'), $payload)
            ->assertRedirect();

        $project = Project::query()
            ->where('company_id', $template->company_id)
            ->where('slug', 'manager-created-residence')
            ->firstOrFail();

        $this->assertSame($manager->id, $project->manager_id);
        $this->assertTrue($project->users()->whereKey($manager->id)->wherePivot('role', 'company_manager')->exists());
    }

    public function test_company_admin_can_create_project_and_attach_existing_client(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $template = TimelineTemplate::query()
            ->whereHas('company', fn ($query) => $query->where('slug', 'omni-pool-builders'))
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                ...$this->projectCreatePayload($template, $manager->id),
                'name' => 'Existing Client Retreat',
                'client_name' => 'Avery Smith',
                'client_email' => $client->email,
            ])
            ->assertRedirect();

        $project = Project::query()
            ->where('company_id', $template->company_id)
            ->where('slug', 'existing-client-retreat')
            ->firstOrFail();

        $this->assertTrue($project->users()->whereKey($client->id)->wherePivot('role', 'client')->exists());
        $this->assertSame('Avery Smith', $project->client_name);
        $this->assertSame($client->email, $project->client_email);
        $this->assertFalse(Invitation::query()
            ->where('project_id', $project->id)
            ->where('email', $client->email)
            ->exists());
    }

    public function test_project_create_rejects_cross_company_requests(): void
    {
        $this->seed();

        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $template = TimelineTemplate::query()
            ->whereHas('company', fn ($query) => $query->where('slug', 'omni-pool-builders'))
            ->firstOrFail();

        $this->actingAs($otherAdmin)
            ->post(route('projects.store'), $this->projectCreatePayload($template, $manager->id))
            ->assertForbidden();
    }

    public function test_subcontractor_project_index_hides_payments_and_messages(): void
    {
        $this->seed();

        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();

        $this->actingAs($subcontractor)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectsIndex')
                ->where('role', 'subcontractor')
                ->has('rows', 6)
                ->where('rows', fn ($rows) => collect($rows)->every(fn (array $row) => ! array_key_exists('payment_total', $row)
                    && ! array_key_exists('payment_paid', $row)
                    && ! array_key_exists('messages', $row))));
    }

    public function test_client_project_index_redirects_to_primary_project(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->get(route('projects.index'))
            ->assertRedirect(route('projects.show', $project));
    }

    public function test_project_index_is_tenant_safe(): void
    {
        $this->seed();

        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();

        $this->actingAs($otherAdmin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectsIndex')
                ->where('rows', fn ($rows) => collect($rows)
                    ->contains(fn (array $row) => $row['slug'] === 'canyon-courtyard')
                    && ! collect($rows)->contains(fn (array $row) => $row['slug'] === 'smith-residence')));
    }

    public function test_homeowner_project_page_receives_client_and_upload_permissions(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectDetail')
                ->where('project.client.name', 'Avery Smith')
                ->where('project.permissions.can_upload_documents', true));
    }

    public function test_internal_project_page_receives_operations_payload(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/ProjectDetail')
                ->where('project.project_code', 'PV-1001')
                ->where('project.address_line', '7420 N Silver Palm Drive')
                ->where('project.permissions.can_update_project', true)
                ->where('project.permissions.can_upload_media', true)
                ->where('project.permissions.can_manage_subcontractors', true)
                ->has('project.media')
                ->has('project.available_subcontractors'));
    }

    public function test_client_and_subcontractor_do_not_receive_internal_edit_permissions(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.permissions.can_update_project', false)
                ->where('project.permissions.can_upload_media', false)
                ->where('project.permissions.can_manage_subcontractors', false)
                ->where('project.available_subcontractors', []));

        $this->actingAs($subcontractor)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.permissions.can_update_project', false)
                ->where('project.permissions.can_upload_media', false)
                ->where('project.permissions.can_manage_subcontractors', false)
                ->where('project.payments', [])
                ->where('project.threads', [])
                ->where('project.available_subcontractors', []));
    }

    public function test_company_admin_and_manager_can_update_project_details_and_customer_name(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('projects.update', $project), [
                'customer_name' => 'Jordan Smith',
                'customer_email' => 'jordan.smith@example.com',
                'address_line' => '777 Updated Ridge Road',
                'city' => 'Scottsdale',
                'state' => 'Arizona',
                'postal_code' => '85255',
                'contract_amount' => '132500',
                'contract_signed_on' => $project->contract_signed_on->toDateString(),
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('777 Updated Ridge Road', $project->address_line);
        $this->assertSame('132500.00', $project->contract_amount);
        $this->assertSame('Jordan Smith', $project->client_name);
        $this->assertSame('jordan.smith@example.com', $project->client_email);
        $this->assertSame('Jordan Smith', User::query()->where('email', 'client@omnipools.test')->firstOrFail()->name);

        $this->actingAs($manager)
            ->patch(route('projects.update', $project), [
                'customer_name' => 'Avery Smith',
                'customer_email' => 'avery.smith@example.com',
                'address_line' => '1234 Desert Ridge Road',
                'city' => 'Scottsdale',
                'state' => 'Arizona',
                'postal_code' => '85255',
                'contract_amount' => '125000',
                'contract_signed_on' => $project->contract_signed_on->toDateString(),
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('Avery Smith', $project->client_name);
        $this->assertSame('avery.smith@example.com', $project->client_email);
    }

    public function test_company_admin_and_manager_can_delete_projects(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $adminProject = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $managerProject = Project::query()->where('slug', 'johnson-residence')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('projects.destroy', $adminProject))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $adminProject->id]);

        $this->actingAs($manager)
            ->delete(route('projects.destroy', $managerProject))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $managerProject->id]);
    }

    public function test_non_internal_users_cannot_update_project_details(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $payload = [
            'customer_name' => 'Blocked User',
            'customer_email' => 'blocked@example.com',
            'address_line' => 'Nope',
            'city' => 'Scottsdale',
            'state' => 'Arizona',
            'postal_code' => '85255',
            'contract_amount' => '1',
            'contract_signed_on' => $project->contract_signed_on->toDateString(),
        ];

        $this->actingAs($client)->patch(route('projects.update', $project), $payload)->assertForbidden();
        $this->actingAs($subcontractor)->patch(route('projects.update', $project), $payload)->assertForbidden();
        $this->actingAs($otherAdmin)->patch(route('projects.update', $project), $payload)->assertForbidden();
    }

    public function test_non_internal_users_cannot_delete_project(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)->delete(route('projects.destroy', $project))->assertForbidden();
        $this->actingAs($subcontractor)->delete(route('projects.destroy', $project))->assertForbidden();
        $this->actingAs($otherAdmin)->delete(route('projects.destroy', $project))->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    public function test_internal_user_and_homeowner_can_upload_project_documents(): void
    {
        Storage::fake('public');
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('projects.documents.store', $project), [
                'document' => UploadedFile::fake()->create('field-photo.jpg', 128, 'image/jpeg'),
                'title' => 'Field Photo',
                'category' => 'Progress',
            ])
            ->assertRedirect();

        $this->actingAs($client)
            ->post(route('projects.documents.store', $project), [
                'document' => UploadedFile::fake()->create('homeowner-file.pdf', 128, 'application/pdf'),
                'title' => 'Homeowner File',
            ])
            ->assertRedirect();

        $documents = ProjectDocument::query()
            ->where('project_id', $project->id)
            ->whereIn('title', ['Field Photo', 'Homeowner File'])
            ->get();

        $this->assertCount(2, $documents);
        $this->assertTrue($documents->every(fn (ProjectDocument $document) => $document->client_visible === true
            && $document->subcontractor_visible === false
            && $document->visibility === 'client'));

        $documents->each(fn (ProjectDocument $document) => Storage::disk('public')->assertExists($document->path));
    }

    public function test_homeowner_can_open_client_visible_uploaded_project_document(): void
    {
        Storage::fake('public');
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->post(route('projects.documents.store', $project), [
                'document' => UploadedFile::fake()->image('homeowner-progress.jpg'),
                'title' => 'Homeowner Progress',
            ])
            ->assertRedirect();

        $document = ProjectDocument::query()
            ->where('project_id', $project->id)
            ->where('title', 'Homeowner Progress')
            ->firstOrFail();

        $response = $this->actingAs($client)
            ->get(route('projects.documents.show', [$project, $document]));

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('content-type'));

        $storageResponse = $this->actingAs($client)
            ->get('/storage/'.$document->path);

        $storageResponse->assertOk();
        $this->assertSame('image/jpeg', $storageResponse->headers->get('content-type'));
    }

    public function test_subcontractor_cannot_open_client_visible_private_upload(): void
    {
        Storage::fake('public');
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->post(route('projects.documents.store', $project), [
                'document' => UploadedFile::fake()->create('client-private.pdf', 128, 'application/pdf'),
                'title' => 'Client Private Upload',
            ])
            ->assertRedirect();

        $document = ProjectDocument::query()
            ->where('project_id', $project->id)
            ->where('title', 'Client Private Upload')
            ->firstOrFail();

        $this->actingAs($subcontractor)
            ->get(route('projects.documents.show', [$project, $document]))
            ->assertForbidden();

        $this->actingAs($subcontractor)
            ->get('/storage/'.$document->path)
            ->assertForbidden();
    }

    public function test_project_document_stream_route_rejects_cross_project_documents(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $otherDocument = ProjectDocument::query()
            ->where('project_id', '!=', $project->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('projects.documents.show', [$project, $otherDocument]))
            ->assertNotFound();
    }

    public function test_internal_users_can_upload_project_photos_visible_to_project_roles(): void
    {
        Storage::fake('public');
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('projects.media.store', $project), [
                'photo' => UploadedFile::fake()->image('waterline.jpg'),
                'alt_text' => 'Waterline progress',
            ])
            ->assertRedirect();

        $asset = MediaAsset::query()
            ->where('project_id', $project->id)
            ->where('alt_text', 'Waterline progress')
            ->firstOrFail();

        Storage::disk('public')->assertExists($asset->path);

        foreach ([$manager, $client, $subcontractor] as $user) {
            $this->actingAs($user)
                ->get(route('projects.show', $project))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('project.media', fn ($media) => collect($media)
                        ->contains(fn (array $photo) => $photo['id'] === $asset->id)));

            $this->actingAs($user)
                ->get(route('projects.media.show', [$project, $asset]))
                ->assertOk();
        }
    }

    public function test_client_and_subcontractor_cannot_upload_project_photos(): void
    {
        Storage::fake('public');
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->post(route('projects.media.store', $project), [
                'photo' => UploadedFile::fake()->image('client-photo.jpg'),
            ])
            ->assertForbidden();

        $this->actingAs($subcontractor)
            ->post(route('projects.media.store', $project), [
                'photo' => UploadedFile::fake()->image('sub-photo.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_internal_users_can_sync_subcontractors_without_removing_client_or_manager(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('projects.subcontractors.update', $project), [
                'subcontractor_ids' => [],
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertFalse($project->users()->whereKey($subcontractor->id)->wherePivot('role', 'subcontractor')->exists());
        $this->assertTrue($project->users()->whereKey($manager->id)->wherePivot('role', 'company_manager')->exists());
        $this->assertTrue($project->users()->whereKey($client->id)->wherePivot('role', 'client')->exists());

        $this->actingAs($admin)
            ->patch(route('projects.subcontractors.update', $project), [
                'subcontractor_ids' => [$subcontractor->id],
            ])
            ->assertRedirect();

        $this->assertTrue($project->users()->whereKey($subcontractor->id)->wherePivot('role', 'subcontractor')->exists());
    }

    public function test_subcontractor_sync_rejects_cross_company_and_non_internal_users(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();
        $otherSubcontractor = User::query()->where('email', 'tile@aurelia.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->patch(route('projects.subcontractors.update', $project), [
                'subcontractor_ids' => [$otherSubcontractor->id],
            ])
            ->assertForbidden();

        $this->actingAs($otherAdmin)
            ->patch(route('projects.subcontractors.update', $project), [
                'subcontractor_ids' => [$otherSubcontractor->id],
            ])
            ->assertForbidden();
    }

    public function test_subcontractor_cannot_upload_project_documents(): void
    {
        Storage::fake('public');
        $this->seed();

        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($subcontractor)
            ->post(route('projects.documents.store', $project), [
                'document' => UploadedFile::fake()->create('sub-file.pdf', 128, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_homeowner_uploads_are_hidden_from_subcontractors(): void
    {
        Storage::fake('public');
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $subcontractor = User::query()->where('email', 'sub@omnipools.test')->firstOrFail();
        $project = Project::query()->where('slug', 'smith-residence')->firstOrFail();

        $this->actingAs($client)
            ->post(route('projects.documents.store', $project), [
                'document' => UploadedFile::fake()->create('client-private.pdf', 128, 'application/pdf'),
                'title' => 'Client Private Upload',
            ])
            ->assertRedirect();

        $this->actingAs($subcontractor)
            ->get(route('projects.documents', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectVista/Documents')
                ->where('project.documents', fn ($documents) => collect($documents)
                    ->doesntContain(fn (array $document) => $document['title'] === 'Client Private Upload')));
    }

    /**
     * @return array<string, mixed>
     */
    private function projectCreatePayload(TimelineTemplate $template, int $managerId): array
    {
        return [
            'company_id' => $template->company_id,
            'timeline_template_id' => $template->id,
            'manager_id' => $managerId,
            'name' => 'New Scottsdale Residence',
            'client_name' => 'Taylor Client',
            'client_email' => 'new-client@example.com',
            'address_line' => '123 New Build Lane',
            'city' => 'Scottsdale',
            'state' => 'Arizona',
            'postal_code' => '85255',
            'contract_amount' => '225000',
            'contract_signed_on' => '2026-01-10',
            'client_summary' => 'A new ProjectVista client portal project.',
            'latest_update' => 'Project created.',
            'next_step' => 'Schedule kickoff.',
            'subcontractor_ids' => [],
        ];
    }
}
