<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectVista;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectVista\ReorderTimelineRequest;
use App\Http\Requests\ProjectVista\RespondApprovalRequest;
use App\Http\Requests\ProjectVista\RespondSelectionRequest;
use App\Http\Requests\ProjectVista\StoreMessageRequest;
use App\Http\Requests\ProjectVista\StoreProjectDocumentRequest;
use App\Http\Requests\ProjectVista\StoreProjectMediaRequest;
use App\Http\Requests\ProjectVista\UpdateProjectRequest;
use App\Http\Requests\ProjectVista\UpdateProjectSubcontractorsRequest;
use App\Models\Approval;
use App\Models\MediaAsset;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\PaymentMilestone;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Selection;
use App\Models\TimelineTask;
use App\Models\User;
use App\Support\ProjectVista\ProjectVistaData;
use App\Support\ProjectVista\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProjectController extends Controller
{
    public function index(Request $request, ProjectVistaData $data): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Project::class);

        $payload = $data->projectIndex($request->user());

        if ($payload['role'] === Roles::CLIENT && $payload['primaryProject'] !== null) {
            return redirect()->route('projects.show', $payload['primaryProject']['slug']);
        }

        return Inertia::render('ProjectVista/ProjectsIndex', $payload);
    }

    public function show(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('ProjectVista/ProjectDetail', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validated();

        DB::transaction(function () use ($project, $data): void {
            $project->update([
                'address_line' => $data['address_line'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'] ?? null,
                'contract_amount' => $data['contract_amount'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'estimated_completion_on' => $data['estimated_completion_on'] ?? null,
                'project_type' => $data['project_type'],
                'status' => $data['status'],
                'phase' => $data['phase'],
            ]);

            $customerName = trim((string) ($data['customer_name'] ?? ''));

            if ($customerName !== '') {
                $client = $project->users()
                    ->wherePivot('role', Roles::CLIENT)
                    ->first();

                $client?->update(['name' => $customerName]);
            }
        });

        return back()->with('success', 'Project details saved.');
    }

    public function timeline(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('ProjectVista/Timeline', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function selections(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('ProjectVista/Selections', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function approvals(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);
        abort_if(auth()->user()->projectRole($project) === Roles::SUBCONTRACTOR, 403);

        return Inertia::render('ProjectVista/Approvals', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function payments(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);
        abort_if(auth()->user()->projectRole($project) === Roles::SUBCONTRACTOR, 403);

        return Inertia::render('ProjectVista/Payments', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function documents(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('ProjectVista/Documents', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function storeDocument(StoreProjectDocumentRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('upload', [ProjectDocument::class, $project]);

        $file = $request->file('document');
        $path = $file->store('project-documents/'.$project->id, 'public');

        if ($path === false) {
            return back()->with('error', 'Document upload failed.');
        }

        $title = $request->string('title')->trim()->toString()
            ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        ProjectDocument::query()->create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'uploaded_by_id' => $request->user()->id,
            'title' => $title,
            'category' => $request->string('category')->trim()->toString() ?: 'Uploads',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'visibility' => 'client',
            'client_visible' => true,
            'subcontractor_visible' => false,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function storeMedia(StoreProjectMediaRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $file = $request->file('photo');
        $path = $file->store('project-media/'.$project->id, 'public');

        if ($path === false) {
            return back()->with('error', 'Photo upload failed.');
        }

        MediaAsset::query()->create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'uploaded_by_id' => $request->user()->id,
            'collection' => 'project',
            'kind' => 'image',
            'disk' => 'public',
            'path' => $path,
            'alt_text' => $request->string('alt_text')->trim()->toString()
                ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]);

        return back()->with('success', 'Project photo uploaded.');
    }

    public function showMedia(Project $project, MediaAsset $mediaAsset): StreamedResponse
    {
        Gate::authorize('view', $project);
        abort_unless($mediaAsset->project_id === $project->id, 404);
        Gate::authorize('view', $mediaAsset);

        $disk = Storage::disk($mediaAsset->disk);
        abort_unless($disk->exists($mediaAsset->path), 404);

        $extension = pathinfo($mediaAsset->path, PATHINFO_EXTENSION);
        $baseName = Str::slug($mediaAsset->alt_text ?? 'project-photo') ?: 'project-photo';
        $filename = $extension === '' ? $baseName : $baseName.'.'.$extension;

        return $disk->response($mediaAsset->path, $filename, [
            'Content-Type' => $disk->mimeType($mediaAsset->path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function updateSubcontractors(UpdateProjectSubcontractorsRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $requestedIds = collect($request->validated('subcontractor_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $companyMemberships = DB::table('company_user')
            ->where('company_id', $project->company_id)
            ->where('role', Roles::SUBCONTRACTOR)
            ->whereIn('user_id', $requestedIds)
            ->get()
            ->keyBy('user_id');

        $companySubcontractors = User::query()
            ->whereIn('id', $companyMemberships->keys())
            ->get();

        $syncPayload = $companySubcontractors
            ->mapWithKeys(function (User $subcontractor) use ($project, $companyMemberships): array {
                $existing = $project->users()
                    ->whereKey($subcontractor->id)
                    ->wherePivot('role', Roles::SUBCONTRACTOR)
                    ->first();
                $companyMembership = $companyMemberships->get($subcontractor->id);

                return [
                    $subcontractor->id => [
                        'role' => Roles::SUBCONTRACTOR,
                        'assigned_scope' => $existing?->pivot?->assigned_scope
                            ?: $companyMembership?->title
                            ?: 'Assigned Trade Partner',
                        'permissions' => json_encode(['timeline', 'approved_selections', 'visible_documents']),
                    ],
                ];
            })
            ->all();

        DB::transaction(function () use ($project, $syncPayload): void {
            $existingSubcontractorIds = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('role', Roles::SUBCONTRACTOR)
                ->pluck('user_id')
                ->all();

            if ($existingSubcontractorIds !== []) {
                $project->users()->detach($existingSubcontractorIds);
            }

            if ($syncPayload !== []) {
                $project->users()->attach($syncPayload);
            }
        });

        return back()->with('success', 'Subcontractor assignments saved.');
    }

    public function showDocument(Project $project, ProjectDocument $document): StreamedResponse
    {
        return $this->streamDocument($project, $document);
    }

    public function showDocumentFromStoragePath(int $projectId, string $filename): StreamedResponse
    {
        $project = Project::query()->findOrFail($projectId);
        $document = ProjectDocument::query()
            ->where('project_id', $project->id)
            ->where('path', 'project-documents/'.$project->id.'/'.$filename)
            ->firstOrFail();

        return $this->streamDocument($project, $document);
    }

    private function streamDocument(Project $project, ProjectDocument $document): StreamedResponse
    {
        Gate::authorize('view', $project);
        abort_unless($document->project_id === $project->id, 404);
        Gate::authorize('view', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);
        $baseName = Str::slug($document->title) ?: 'project-document';
        $filename = $extension === '' ? $baseName : $baseName.'.'.$extension;

        return $disk->response($document->path, $filename, [
            'Content-Type' => $document->mime_type ?: $disk->mimeType($document->path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function messages(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);
        abort_if(auth()->user()->projectRole($project) === Roles::SUBCONTRACTOR, 403);

        return Inertia::render('ProjectVista/Messages', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function reorderTimeline(ReorderTimelineRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        DB::transaction(function () use ($request, $project): void {
            foreach ($request->validated('tasks') as $task) {
                TimelineTask::query()
                    ->where('project_id', $project->id)
                    ->whereKey($task['id'])
                    ->update(['sort_order' => $task['sort_order']]);
            }
        });

        return back()->with('success', 'Timeline order saved.');
    }

    public function respondSelection(RespondSelectionRequest $request, Selection $selection): RedirectResponse
    {
        Gate::authorize('respond', $selection);

        $selection->update([
            'status' => $request->string('status')->toString(),
            'client_response' => $request->string('client_response')->toString() ?: null,
            'approved_by' => $request->string('status')->toString() === 'approved' ? $request->user()->id : null,
            'approved_at' => $request->string('status')->toString() === 'approved' ? now() : null,
        ]);

        return back()->with('success', 'Selection response saved.');
    }

    public function respondApproval(RespondApprovalRequest $request, Approval $approval): RedirectResponse
    {
        Gate::authorize('respond', $approval);

        $approval->update([
            'status' => $request->string('status')->toString(),
            'responded_by_id' => $request->user()->id,
            'responded_at' => now(),
            'response_note' => $request->string('response_note')->toString() ?: null,
        ]);

        if ($approval->selection_id !== null) {
            $approval->selection()->update([
                'status' => $request->string('status')->toString(),
                'approved_by' => $request->string('status')->toString() === 'approved' ? $request->user()->id : null,
                'approved_at' => $request->string('status')->toString() === 'approved' ? now() : null,
            ]);
        }

        return back()->with('success', 'Approval response saved.');
    }

    public function completePayment(PaymentMilestone $paymentMilestone): RedirectResponse
    {
        Gate::authorize('update', $paymentMilestone);

        $paymentMilestone->update([
            'status' => 'paid',
            'completed_on' => now(),
        ]);

        return back()->with('success', 'Payment milestone marked paid.');
    }

    public function storeMessage(StoreMessageRequest $request, MessageThread $thread): RedirectResponse
    {
        Gate::authorize('createMessage', $thread);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'company_id' => $thread->company_id,
            'project_id' => $thread->project_id,
            'user_id' => $request->user()->id,
            'body' => $request->string('body')->toString(),
        ]);

        $thread->update(['last_message_at' => now()]);

        return back()->with('success', 'Message sent.');
    }
}
