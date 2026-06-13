<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Project;
use App\Models\ProjectDocument;
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
                ->has('rows', 5)
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
                ->has('rows', 5)
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
}
