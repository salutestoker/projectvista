<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectVista;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectVista\ReorderTimelineRequest;
use App\Http\Requests\ProjectVista\RespondApprovalRequest;
use App\Http\Requests\ProjectVista\RespondSelectionRequest;
use App\Http\Requests\ProjectVista\StoreMessageRequest;
use App\Models\Approval;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\PaymentMilestone;
use App\Models\Project;
use App\Models\Selection;
use App\Models\TimelineTask;
use App\Support\ProjectVista\ProjectVistaData;
use App\Support\ProjectVista\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function show(Project $project, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('ProjectVista/ProjectDetail', [
            'project' => $data->project($project, auth()->user()),
        ]);
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
