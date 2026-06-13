<?php

declare(strict_types=1);

namespace App\Support\ProjectVista;

use App\Models\Approval;
use App\Models\Company;
use App\Models\PaymentMilestone;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Selection;
use App\Models\TimelineTask;
use App\Models\User;
use Illuminate\Support\Collection;

final class ProjectVistaData
{
    public function dashboard(User $user): array
    {
        $projects = Project::query()
            ->forUser($user)
            ->with(['company', 'manager', 'approvals', 'selections', 'timelineTasks', 'paymentMilestones'])
            ->latest()
            ->get();

        $primaryProject = $projects->first();
        $primaryCompany = $primaryProject?->company
            ?? $user->companies()->first()
            ?? Company::query()->first();

        return [
            'role' => $this->roleFor($user, $primaryProject, $primaryCompany),
            'companies' => $this->companiesFor($user),
            'projects' => $projects->map(fn (Project $project) => $this->projectCard($project, $user))->values(),
            'primaryProject' => $primaryProject ? $this->project($primaryProject, $user) : null,
            'stats' => $this->stats($projects),
            'demoAccounts' => [
                ['label' => 'Super Admin', 'email' => 'super@projectvista.test'],
                ['label' => 'Company Admin', 'email' => 'admin@omnipools.test'],
                ['label' => 'Manager', 'email' => 'manager@omnipools.test'],
                ['label' => 'Client', 'email' => 'client@omnipools.test'],
                ['label' => 'Subcontractor', 'email' => 'sub@omnipools.test'],
            ],
        ];
    }

    public function companyAdmin(Company $company): array
    {
        $company->load([
            'users',
            'projects.manager',
            'invitations' => fn ($query) => $query->latest(),
        ]);

        return [
            'company' => $this->company($company),
            'users' => $company->users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'title' => $user->pivot->title,
            ])->values(),
            'projects' => $company->projects->map(fn (Project $project) => $this->projectCard($project, auth()->user()))->values(),
            'invitations' => $company->invitations->map(fn ($invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at?->toFormattedDateString(),
            ])->values(),
        ];
    }

    public function project(Project $project, User $user): array
    {
        $project->load([
            'company',
            'manager',
            'users',
            'timelineTasks',
            'selections.category',
            'approvals.selection',
            'paymentMilestones',
            'documents.uploader',
            'messageThreads.messages.author',
        ]);

        $role = $this->roleFor($user, $project, $project->company);
        $internal = in_array($role, ['super_admin', Roles::COMPANY_ADMIN, Roles::COMPANY_MANAGER], true);
        $client = $role === Roles::CLIENT;
        $subcontractor = $role === Roles::SUBCONTRACTOR;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'company' => $this->company($project->company),
            'manager' => $project->manager ? [
                'id' => $project->manager->id,
                'name' => $project->manager->name,
                'email' => $project->manager->email,
            ] : null,
            'address' => "{$project->address_line}, {$project->city}, {$project->state}",
            'project_type' => $project->project_type,
            'status' => $project->status,
            'phase' => $project->phase,
            'percent_complete' => $project->percent_complete,
            'health_status' => $project->health_status,
            'contract_amount' => $project->contract_amount,
            'starts_on' => $project->starts_on?->toFormattedDateString(),
            'estimated_completion_on' => $project->estimated_completion_on?->toFormattedDateString(),
            'hero_image_url' => $project->hero_image_path ? asset('storage/'.$project->hero_image_path) : null,
            'client_summary' => $project->client_summary,
            'latest_update' => $project->latest_update,
            'next_step' => $project->next_step,
            'role' => $role,
            'permissions' => [
                'can_edit_project' => $internal,
                'can_manage_standards' => in_array($role, ['super_admin', Roles::COMPANY_ADMIN], true),
                'can_message' => ! $subcontractor,
                'can_view_payments' => ! $subcontractor,
            ],
            'timeline' => $project->timelineTasks
                ->filter(fn (TimelineTask $task) => $internal || ($client && $task->client_visible) || ($subcontractor && $task->subcontractor_visible))
                ->map(fn (TimelineTask $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'phase' => $task->phase,
                    'description' => $task->description,
                    'sort_order' => $task->sort_order,
                    'status' => $task->status,
                    'starts_on' => $task->starts_on?->toFormattedDateString(),
                    'due_on' => $task->due_on?->toFormattedDateString(),
                    'completed_on' => $task->completed_on?->toFormattedDateString(),
                    'client_visible' => $task->client_visible,
                    'subcontractor_visible' => $task->subcontractor_visible,
                    'requires_acknowledgement' => $task->requires_acknowledgement,
                ])->values(),
            'selections' => $project->selections
                ->filter(fn (Selection $selection) => ! $subcontractor || $selection->status === 'approved')
                ->map(fn (Selection $selection) => [
                    'id' => $selection->id,
                    'category' => $selection->category?->name,
                    'name' => $selection->name,
                    'description' => $selection->description,
                    'image_url' => $selection->image_path ? asset('storage/'.$selection->image_path) : null,
                    'status' => $selection->status,
                    'manager_note' => $selection->manager_note,
                    'client_response' => $selection->client_response,
                    'due_on' => $selection->due_on?->toFormattedDateString(),
                    'approved_at' => $selection->approved_at?->toFormattedDateString(),
                ])->values(),
            'approvals' => $subcontractor ? [] : $project->approvals
                ->map(fn (Approval $approval) => [
                    'id' => $approval->id,
                    'title' => $approval->title,
                    'body' => $approval->body,
                    'status' => $approval->status,
                    'due_on' => $approval->due_on?->toFormattedDateString(),
                    'response_note' => $approval->response_note,
                    'responded_at' => $approval->responded_at?->toFormattedDateString(),
                    'selection' => $approval->selection?->name,
                ])->values(),
            'payments' => $subcontractor ? [] : $project->paymentMilestones
                ->filter(fn (PaymentMilestone $payment) => $internal || $payment->client_visible)
                ->map(fn (PaymentMilestone $payment) => [
                    'id' => $payment->id,
                    'label' => $payment->label,
                    'description' => $payment->description,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'due_on' => $payment->due_on?->toFormattedDateString(),
                    'completed_on' => $payment->completed_on?->toFormattedDateString(),
                    'payment_url' => $payment->payment_url,
                    'provider_label' => $payment->provider_label,
                ])->values(),
            'documents' => $project->documents
                ->filter(fn (ProjectDocument $document) => $internal || ($client && $document->client_visible) || ($subcontractor && $document->subcontractor_visible))
                ->map(fn (ProjectDocument $document) => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'category' => $document->category,
                    'visibility' => $document->visibility,
                    'version' => $document->version,
                    'client_visible' => $document->client_visible,
                    'subcontractor_visible' => $document->subcontractor_visible,
                    'url' => asset('storage/'.$document->path),
                    'uploaded_by' => $document->uploader?->name,
                    'updated_at' => $document->updated_at->toFormattedDateString(),
                ])->values(),
            'threads' => $subcontractor ? [] : $project->messageThreads->map(fn ($thread) => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'status' => $thread->status,
                'last_message_at' => $thread->last_message_at?->diffForHumans(),
                'messages' => $thread->messages->sortBy('created_at')->map(fn ($message) => [
                    'id' => $message->id,
                    'author' => $message->author->name,
                    'author_id' => $message->author->id,
                    'body' => $message->body,
                    'created_at' => $message->created_at->diffForHumans(),
                ])->values(),
            ])->values(),
            'team' => $project->users->map(fn (User $projectUser) => [
                'id' => $projectUser->id,
                'name' => $projectUser->name,
                'email' => $projectUser->email,
                'role' => $projectUser->pivot->role,
                'assigned_scope' => $projectUser->pivot->assigned_scope,
            ])->values(),
        ];
    }

    private function companiesFor(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Company::query()
                ->withCount(['projects', 'users'])
                ->get()
                ->map(fn (Company $company) => $this->company($company) + [
                    'projects_count' => $company->projects_count,
                    'users_count' => $company->users_count,
                ])->all();
        }

        return $user->companies->map(fn (Company $company) => $this->company($company))->all();
    }

    private function company(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'plan' => $company->plan,
            'subscription_status' => $company->subscription_status,
            'brand_primary_color' => $company->brand_primary_color,
            'brand_accent_color' => $company->brand_accent_color,
            'feature_flags' => $company->feature_flags,
        ];
    }

    private function projectCard(Project $project, User $user): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'company' => $project->company?->name,
            'phase' => $project->phase,
            'status' => $project->status,
            'percent_complete' => $project->percent_complete,
            'health_status' => $project->health_status,
            'hero_image_url' => $project->hero_image_path ? asset('storage/'.$project->hero_image_path) : null,
            'pending_approvals' => $project->approvals->where('status', 'pending')->count(),
            'pending_selections' => $project->selections->where('status', 'waiting_client')->count(),
            'blocked_tasks' => $project->timelineTasks->where('status', 'blocked')->count(),
            'role' => $this->roleFor($user, $project, $project->company),
        ];
    }

    private function roleFor(User $user, ?Project $project, ?Company $company): string
    {
        if ($user->isSuperAdmin()) {
            return 'super_admin';
        }

        if ($project !== null) {
            $projectRole = $user->projectRole($project);

            if ($projectRole !== null && $projectRole !== Roles::COMPANY_MANAGER) {
                return $projectRole;
            }
        }

        if ($company !== null) {
            $companyRole = $user->companyRole($company);

            if ($companyRole !== null) {
                return $companyRole;
            }
        }

        return $project !== null ? ($user->projectRole($project) ?? 'viewer') : 'viewer';
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function stats(Collection $projects): array
    {
        return [
            'active_projects' => $projects->where('status', 'active')->count(),
            'pending_approvals' => $projects->flatMap->approvals->where('status', 'pending')->count(),
            'pending_selections' => $projects->flatMap->selections->where('status', 'waiting_client')->count(),
            'blocked_tasks' => $projects->flatMap->timelineTasks->where('status', 'blocked')->count(),
        ];
    }
}
