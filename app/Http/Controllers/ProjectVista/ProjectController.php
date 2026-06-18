<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectVista;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectVista\PreviewTimelineTaskRequest;
use App\Http\Requests\ProjectVista\ReorderTimelineRequest;
use App\Http\Requests\ProjectVista\RespondApprovalRequest;
use App\Http\Requests\ProjectVista\RespondSelectionRequest;
use App\Http\Requests\ProjectVista\StoreMessageRequest;
use App\Http\Requests\ProjectVista\StoreProjectDocumentRequest;
use App\Http\Requests\ProjectVista\StoreProjectMediaRequest;
use App\Http\Requests\ProjectVista\StoreProjectRequest;
use App\Http\Requests\ProjectVista\StoreTimelineTaskRequest;
use App\Http\Requests\ProjectVista\StoreTimelineTaskBlockRequest;
use App\Http\Requests\ProjectVista\UpdateScheduleLockRequest;
use App\Http\Requests\ProjectVista\UpdateProjectRequest;
use App\Http\Requests\ProjectVista\UpdateProjectSubcontractorsRequest;
use App\Http\Requests\ProjectVista\UpdateTimelineTaskRequest;
use App\Models\Approval;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\MediaAsset;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\PaymentMilestone;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ScheduleChangeLog;
use App\Models\Selection;
use App\Models\TimelineTask;
use App\Models\TimelineTaskBlock;
use App\Models\TimelineTemplate;
use App\Models\User;
use App\Services\Scheduling\ProjectTimelineScheduler;
use App\Support\ProjectVista\ProjectVistaData;
use App\Support\ProjectVista\Roles;
use App\Support\ProjectVista\TimelineScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function create(Request $request, ProjectVistaData $data): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('ProjectVista/ProjectCreate', $data->projectCreate($request->user()));
    }

    public function store(
        StoreProjectRequest $request,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('create', Project::class);

        $data = $request->validated();
        $company = Company::query()->findOrFail($data['company_id']);
        $user = $request->user();

        abort_unless($user->isSuperAdmin()
            || in_array($user->companyRole($company), Roles::INTERNAL_ROLES, true), 403);

        $timelineTemplate = TimelineTemplate::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['timeline_template_id']);
        $managerId = $this->managerIdForNewProject($user, $company, $data['manager_id'] ?? null);

        $project = DB::transaction(function () use ($request, $data, $company, $timelineTemplate, $projectTimelineScheduler, $managerId): Project {
            $project = Project::query()->create([
                'company_id' => $company->id,
                'manager_id' => $managerId,
                'name' => $data['name'],
                'slug' => $this->uniqueProjectSlug($company, $data['name']),
                'address_line' => $data['address_line'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'] ?? null,
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'percent_complete' => 0,
                'health_status' => 'on_track',
                'contract_amount' => $data['contract_amount'] ?? null,
                'contract_signed_on' => $data['contract_signed_on'] ?? null,
                'client_summary' => $data['client_summary'] ?? null,
                'latest_update' => $data['latest_update'] ?? null,
                'next_step' => $data['next_step'] ?? null,
            ]);

            $this->attachProjectManager($project, $managerId);
            $this->attachProjectClient(
                $project,
                $data['client_name'],
                $data['client_email'],
                $request->user(),
            );

            foreach ($data['subcontractor_ids'] ?? [] as $subcontractorId) {
                $this->ensureSubcontractorProjectAssignment($project, $subcontractorId);
            }

            $projectTimelineScheduler->createTimelineFromTemplate($project, $timelineTemplate);

            return $project;
        });

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($company->refresh());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created.');
    }

    public function show(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('ProjectVista/ProjectDetail', [
            'project' => $data->project($project, auth()->user()),
        ]);
    }

    public function update(
        UpdateProjectRequest $request,
        Project $project,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('update', $project);

        $data = $request->validated();

        DB::transaction(function () use ($project, $data, $projectTimelineScheduler): void {
            $project->update([
                'address_line' => $data['address_line'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'] ?? null,
                'client_name' => $data['customer_name'] ?? $project->client_name,
                'client_email' => $data['customer_email'] ?? $project->client_email,
                'contract_amount' => $data['contract_amount'] ?? null,
                'contract_signed_on' => $data['contract_signed_on'] ?? null,
            ]);

            $customerName = trim((string) ($data['customer_name'] ?? ''));

            if ($customerName !== '') {
                $client = $project->users()
                    ->wherePivot('role', Roles::CLIENT)
                    ->first();

                $client?->update(['name' => $customerName]);
            }

            $project->refresh();

            if (! $project->timelineTasks()->exists()) {
                $projectTimelineScheduler->createDefaultTimeline($project);
            }
        });

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($project->company()->firstOrFail());

        return back()->with('success', 'Project details saved.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted.');
    }

    public function timelines(Request $request, ProjectVistaData $data): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Project::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->isSuperAdmin() || $this->internalCompanyIds($user)->isNotEmpty(), 403);

        $project = $this->timelineContextProject($user);

        if ($project === null) {
            return redirect()
                ->route('projects.index')
                ->with('error', 'Create a project before viewing timelines.');
        }

        Gate::authorize('view', $project);

        return Inertia::render('ProjectVista/Timeline', [
            'project' => $data->project($project, $user),
            'timeline' => $data->timeline($project, $user),
        ]);
    }

    public function timeline(Project $project, Request $request, ProjectVistaData $data): Response|RedirectResponse
    {
        Gate::authorize('view', $project);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($this->usesInternalTimelineRoute($user, $project)) {
            return redirect()->route('timelines.index');
        }

        return Inertia::render('ProjectVista/Timeline', [
            'project' => $data->project($project, $user),
            'timeline' => $data->timeline($project, $user),
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

        $taskIds = collect($request->validated('tasks'))->pluck('id');

        abort_if(TimelineTask::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $taskIds)
            ->where('is_system', true)
            ->exists(), 403);

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

    public function storeTimelineTask(
        StoreTimelineTaskRequest $request,
        Project $project,
        TimelineScheduler $scheduler,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('update', $project);

        $targetProject = Project::query()
            ->where('company_id', $project->company_id)
            ->findOrFail($request->integer('project_id'));

        abort_unless($scheduler->editableProjectIds($project, $request->user())->contains($targetProject->id), 403);

        $predecessor = TimelineTask::query()
            ->where('project_id', $targetProject->id)
            ->findOrFail($request->integer('predecessor_task_id'));
        $nextSortOrder = (int) TimelineTask::query()
            ->where('project_id', $targetProject->id)
            ->max('sort_order') + 1;

        $data = [
            ...$this->timelineTaskData($request->validated(), $targetProject),
            'sort_order' => $nextSortOrder,
            'sequence_order' => $nextSortOrder,
        ];

        $task = DB::transaction(function () use ($request, $data, $targetProject, $predecessor, $projectTimelineScheduler): TimelineTask {
            $task = TimelineTask::query()->create([
                ...$data,
                'company_id' => $targetProject->company_id,
                'project_id' => $targetProject->id,
                'created_by_id' => $request->user()->id,
                'updated_by_id' => $request->user()->id,
            ]);

            $this->logScheduleChange($task, $request->user(), $data, 0, false);
            $this->ensureSubcontractorProjectAssignment($targetProject, $data['assigned_subcontractor_id'] ?? null);

            return $projectTimelineScheduler->insertTaskAfter($task, $predecessor, false);
        });

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($targetProject->company()->firstOrFail());

        return back()
            ->with('success', 'Timeline task added.')
            ->with('timeline_conflicts', [])
            ->with('selected_timeline_task_id', $task->id);
    }

    public function updateTimelineTask(
        UpdateTimelineTaskRequest $request,
        Project $project,
        TimelineTask $task,
        TimelineScheduler $scheduler,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('update', $task);

        abort_unless($task->company_id === $project->company_id, 404);
        abort_unless($scheduler->editableProjectIds($project, $request->user())->contains($task->project_id), 403);
        abort_if($task->is_system, 403);

        $taskProject = $task->project()->firstOrFail();
        $data = $this->timelineTaskData($request->validated(), $taskProject, $task);

        DB::transaction(function () use ($request, $task, $taskProject, $data, $projectTimelineScheduler): void {
            $this->logScheduleChange($task, $request->user(), $data, 0, false);
            $task->update([
                ...$data,
                'updated_by_id' => $request->user()->id,
            ]);
            $this->ensureSubcontractorProjectAssignment($taskProject, $data['assigned_subcontractor_id'] ?? null);
        });

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($taskProject->company()->firstOrFail());

        return back()
            ->with('success', 'Timeline task saved.')
            ->with('timeline_conflicts', [])
            ->with('selected_timeline_task_id', $task->id);
    }

    public function destroyTimelineTask(
        Request $request,
        Project $project,
        TimelineTask $task,
        TimelineScheduler $scheduler,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('delete', $task);

        abort_unless($task->company_id === $project->company_id, 404);
        abort_unless($task->project_id !== null, 404);
        abort_unless($scheduler->editableProjectIds($project, $request->user())->contains($task->project_id), 403);
        abort_if($task->is_system, 403);

        $taskProject = $task->project()->firstOrFail();

        DB::transaction(function () use ($task, $taskProject, $projectTimelineScheduler): void {
            ScheduleChangeLog::query()
                ->where('timeline_task_id', $task->id)
                ->delete();

            $task->delete();
            $projectTimelineScheduler->normalizeProjectTaskOrder($taskProject->refresh());
        });

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($taskProject->company()->firstOrFail());

        return back()->with('success', 'Timeline task deleted.');
    }

    public function storeTimelineTaskBlock(
        StoreTimelineTaskBlockRequest $request,
        Project $project,
        TimelineTask $task,
        TimelineScheduler $scheduler,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('update', $task);

        abort_unless($task->company_id === $project->company_id, 404);
        abort_unless($task->project_id !== null, 404);
        abort_unless($scheduler->editableProjectIds($project, $request->user())->contains($task->project_id), 403);

        TimelineTaskBlock::query()->create([
            'company_id' => $task->company_id,
            'project_id' => $task->project_id,
            'timeline_task_id' => $task->id,
            'created_by_id' => $request->user()->id,
            'type' => $request->string('type')->toString(),
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
        ]);

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($task->company()->firstOrFail());

        return back()->with('success', 'Timeline block added.');
    }

    public function resolveTimelineTaskBlock(
        Request $request,
        Project $project,
        TimelineTask $task,
        TimelineTaskBlock $block,
        TimelineScheduler $scheduler,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('update', $task);

        abort_unless($task->company_id === $project->company_id, 404);
        abort_unless($block->timeline_task_id === $task->id, 404);
        abort_unless($task->project_id !== null, 404);
        abort_unless($scheduler->editableProjectIds($project, $request->user())->contains($task->project_id), 403);

        $block->update([
            'status' => 'resolved',
            'resolved_by_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($task->company()->firstOrFail());

        return back()->with('success', 'Timeline block resolved.');
    }

    public function updateTimelineTaskScheduleLock(
        UpdateScheduleLockRequest $request,
        Project $project,
        TimelineTask $task,
        TimelineScheduler $scheduler,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('update', $task);

        abort_unless($task->company_id === $project->company_id, 404);
        abort_unless($task->project_id !== null, 404);
        abort_unless($scheduler->editableProjectIds($project, $request->user())->contains($task->project_id), 403);

        $validated = $request->validated();
        $task->update([
            'is_schedule_locked' => (bool) $validated['is_schedule_locked'],
            'schedule_locked_reason' => (bool) $validated['is_schedule_locked']
                ? ($validated['schedule_locked_reason'] ?? 'Manager locked schedule.')
                : null,
        ]);

        $projectTimelineScheduler->rescheduleCompanyProjectsByPriority($task->company()->firstOrFail());

        return back()->with('success', (bool) $validated['is_schedule_locked'] ? 'Schedule lock enabled.' : 'Schedule lock removed.');
    }

    public function previewTimelineTask(
        PreviewTimelineTaskRequest $request,
        Project $project,
        TimelineTask $task,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): JsonResponse {
        Gate::authorize('previewConflicts', $task);

        abort_unless($task->company_id === $project->company_id, 404);
        abort_unless($task->project_id !== null, 404);

        $validated = $request->validated();
        $attributes = [
            ...$validated,
            'assigned_subcontractor_id' => $validated['assigned_subcontractor_id'] ?? $task->assigned_subcontractor_id,
            'subcontractor_type_id' => $validated['subcontractor_type_id'] ?? $task->subcontractor_type_id,
            'status' => $validated['status'] ?? $task->status,
            'starts_on' => $validated['starts_on'] ?? $task->starts_on?->toDateString(),
            'due_on' => $validated['due_on'] ?? $task->due_on?->toDateString(),
        ];

        return response()->json($projectTimelineScheduler->previewReschedule($task, $attributes)->toArray());
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function timelineTaskData(array $validated, Project $project, ?TimelineTask $task = null): array
    {
        $status = (string) ($validated['status'] ?? $task?->status ?? 'upcoming');
        $isClosed = in_array($status, TimelineScheduler::CLOSED_STATUSES, true);
        $wasClosed = $task !== null && in_array($task->status, TimelineScheduler::CLOSED_STATUSES, true);
        $completedOn = $isClosed
            ? ($wasClosed ? ($task?->completed_on ?? now()) : now())
            : null;
        $durationDays = isset($validated['duration_days'])
            ? (int) $validated['duration_days']
            : (int) ($task?->default_duration_working_days ?? 1);
        $assignedSubcontractorId = $validated['assigned_subcontractor_id'] ?? $task?->assigned_subcontractor_id;
        $subcontractorTypeId = $validated['subcontractor_type_id'] ?? $task?->subcontractor_type_id;

        if ($assignedSubcontractorId !== null && $subcontractorTypeId === null) {
            $subcontractorTypeId = DB::table('company_user')
                ->where('company_id', $project->company_id)
                ->where('user_id', $assignedSubcontractorId)
                ->where('role', Roles::SUBCONTRACTOR)
                ->value('subcontractor_type_id');
        }

        return [
            'title' => $validated['title'] ?? $task?->title ?? 'Timeline Task',
            'phase' => $validated['phase'] ?? $task?->phase ?? 'Construction',
            'description' => array_key_exists('description', $validated)
                ? ($validated['description'] ?? null)
                : $task?->description,
            'status' => $status,
            'default_duration_working_days' => max(1, $durationDays),
            'priority' => max(1, min(4, (int) ($validated['priority'] ?? $task?->priority ?? 2))),
            'customer_urgency' => max(0, min(4, (int) ($validated['customer_urgency'] ?? $task?->customer_urgency ?? 1))),
            'completed_on' => $completedOn,
            'assigned_subcontractor_id' => $assignedSubcontractorId,
            'subcontractor_type_id' => $subcontractorTypeId,
            'is_schedule_locked' => (bool) ($validated['is_schedule_locked'] ?? $task?->is_schedule_locked ?? false),
            'schedule_locked_reason' => (bool) ($validated['is_schedule_locked'] ?? $task?->is_schedule_locked ?? false)
                ? ($validated['schedule_locked_reason'] ?? $task?->schedule_locked_reason ?? 'Manager locked schedule.')
                : null,
            'internal_only' => (bool) ($validated['internal_only'] ?? $task?->internal_only ?? false),
            'requires_acknowledgement' => (bool) ($validated['requires_acknowledgement'] ?? $task?->requires_acknowledgement ?? false),
            'is_job_site_ready' => (bool) ($validated['is_job_site_ready'] ?? $task?->is_job_site_ready ?? true),
            'are_materials_ready' => (bool) ($validated['are_materials_ready'] ?? $task?->are_materials_ready ?? true),
            'is_customer_approval_required' => (bool) ($validated['is_customer_approval_required'] ?? $task?->is_customer_approval_required ?? false),
            'is_customer_approval_received' => (bool) ($validated['is_customer_approval_received'] ?? $task?->is_customer_approval_received ?? false),
            'internal_notes' => array_key_exists('internal_notes', $validated)
                ? ($validated['internal_notes'] ?? null)
                : $task?->internal_notes,
            'customer_notes' => array_key_exists('customer_notes', $validated)
                ? ($validated['customer_notes'] ?? null)
                : $task?->customer_notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $newData
     */
    private function logScheduleChange(
        TimelineTask $task,
        User $user,
        array $newData,
        int $conflictsDetectedCount,
        bool $blockedByConflicts,
    ): void {
        ScheduleChangeLog::query()->create([
            'company_id' => $task->company_id,
            'project_id' => $task->project_id,
            'timeline_task_id' => $task->id,
            'user_id' => $user->id,
            'old_status' => $task->exists ? $task->status : null,
            'new_status' => $newData['status'] ?? null,
            'old_start_date' => $task->exists ? $task->starts_on : null,
            'new_start_date' => $newData['starts_on'] ?? null,
            'old_end_date' => $task->exists ? $task->due_on : null,
            'new_end_date' => $newData['due_on'] ?? null,
            'old_subcontractor_id' => $task->exists ? $task->assigned_subcontractor_id : null,
            'new_subcontractor_id' => $newData['assigned_subcontractor_id'] ?? null,
            'change_reason' => $blockedByConflicts ? 'Blocked by schedule conflict preview.' : 'Timeline task saved.',
            'conflicts_detected_count' => $conflictsDetectedCount,
            'saved_with_override' => false,
            'blocked_by_conflicts' => $blockedByConflicts,
        ]);
    }

    private function usesInternalTimelineRoute(User $user, Project $project): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($project->company_id), Roles::INTERNAL_ROLES, true);
    }

    /**
     * @return Collection<int, int>
     */
    private function internalCompanyIds(User $user): Collection
    {
        return DB::table('company_user')
            ->where('user_id', $user->id)
            ->whereIn('role', Roles::INTERNAL_ROLES)
            ->pluck('company_id');
    }

    private function timelineContextProject(User $user): ?Project
    {
        $query = Project::query()
            ->with('company')
            ->orderBy('name');

        if ($user->isSuperAdmin()) {
            return $query->first();
        }

        $companyIds = $this->internalCompanyIds($user);

        if ($companyIds->isEmpty()) {
            return null;
        }

        return $query
            ->whereIn('company_id', $companyIds)
            ->first();
    }

    private function managerIdForNewProject(User $user, Company $company, int|string|null $submittedManagerId): ?int
    {
        if ($submittedManagerId !== null && $submittedManagerId !== '') {
            return (int) $submittedManagerId;
        }

        if ($user->companyRole($company) === Roles::COMPANY_MANAGER) {
            return $user->id;
        }

        return null;
    }

    private function uniqueProjectSlug(Company $company, string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'project';
        $slug = $baseSlug;
        $suffix = 2;

        while (Project::withTrashed()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function attachProjectManager(Project $project, int|string|null $managerId): void
    {
        if ($managerId === null || $managerId === '') {
            return;
        }

        DB::table('project_user')->updateOrInsert(
            [
                'project_id' => $project->id,
                'user_id' => (int) $managerId,
                'role' => Roles::COMPANY_MANAGER,
            ],
            [
                'assigned_scope' => 'Full project management',
                'permissions' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function attachProjectClient(
        Project $project,
        string $clientName,
        string $clientEmail,
        User $inviter,
    ): void {
        $client = User::query()
            ->where('email', $clientEmail)
            ->first();

        if ($client !== null) {
            DB::table('project_user')->updateOrInsert(
                [
                    'project_id' => $project->id,
                    'user_id' => $client->id,
                    'role' => Roles::CLIENT,
                ],
                [
                    'assigned_scope' => 'Homeowner portal',
                    'permissions' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            return;
        }

        Invitation::query()->updateOrCreate(
            [
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'email' => $clientEmail,
                'role' => Roles::CLIENT,
                'status' => 'pending',
            ],
            [
                'invited_by_id' => $inviter->id,
                'recipient_name' => $clientName,
                'subcontractor_type_id' => null,
                'token' => Str::random(40),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ],
        );
    }

    private function ensureSubcontractorProjectAssignment(Project $project, int|string|null $subcontractorId): void
    {
        if ($subcontractorId === null || $subcontractorId === '') {
            return;
        }

        $membership = DB::table('company_user')
            ->where('company_id', $project->company_id)
            ->where('user_id', $subcontractorId)
            ->where('role', Roles::SUBCONTRACTOR)
            ->first();

        if ($membership === null) {
            return;
        }

        DB::table('project_user')->updateOrInsert(
            [
                'project_id' => $project->id,
                'user_id' => (int) $subcontractorId,
                'role' => Roles::SUBCONTRACTOR,
            ],
            [
                'assigned_scope' => $membership->title ?: 'Assigned Trade Partner',
                'permissions' => json_encode(['timeline', 'approved_selections', 'visible_documents']),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
