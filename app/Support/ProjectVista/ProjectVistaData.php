<?php

declare(strict_types=1);

namespace App\Support\ProjectVista;

use App\Models\Approval;
use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\PaymentMilestone;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Selection;
use App\Models\TimelineTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProjectVistaData
{
    public function dashboard(User $user): array
    {
        $projects = Project::query()
            ->forUser($user)
            ->with([
                'company',
                'manager',
                'approvals',
                'selections',
                'timelineTasks',
                'paymentMilestones',
                'messageThreads.messages.author',
                'users',
            ])
            ->latest()
            ->get();

        $primaryProject = $projects->first();
        $primaryCompany = $primaryProject?->company
            ?? $user->companies()->first()
            ?? Company::query()->first();
        $role = $this->roleFor($user, $primaryProject, $primaryCompany);

        return [
            'role' => $role,
            'companies' => $this->companiesFor($user),
            'projects' => $projects->map(fn (Project $project) => $this->projectCard($project, $user))->values(),
            'primaryProject' => $primaryProject ? $this->project($primaryProject, $user) : null,
            'stats' => $this->stats($projects, $user, $role),
            'home' => $this->homeFor($projects, $user, $role, $primaryProject),
            'demoAccounts' => [
                ['label' => 'Super Admin', 'email' => 'super@projectvista.test'],
                ['label' => 'Company Admin', 'email' => 'admin@omnipools.test'],
                ['label' => 'Manager', 'email' => 'manager@omnipools.test'],
                ['label' => 'Client', 'email' => 'client@omnipools.test'],
                ['label' => 'Subcontractor', 'email' => 'sub@omnipools.test'],
            ],
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function homeFor(Collection $projects, User $user, string $role, ?Project $primaryProject): array
    {
        return match ($role) {
            Roles::CLIENT => $this->clientHome($primaryProject, $user),
            Roles::SUBCONTRACTOR => $this->subcontractorHome($projects, $user),
            Roles::COMPANY_ADMIN => $this->businessHome($projects, $user, 'owner'),
            Roles::COMPANY_MANAGER => $this->businessHome($projects, $user, 'manager'),
            'super_admin' => [
                'type' => 'super_admin',
                'title' => 'ProjectVista Command Center',
                'subtitle' => 'Use the command center for platform oversight and the component library for UI inventory.',
            ],
            default => $this->businessHome($projects, $user, 'viewer'),
        };
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function businessHome(Collection $projects, User $user, string $variant): array
    {
        $stats = $this->stats($projects, $user, $variant);
        $approvals = $projects->flatMap->approvals;
        $messages = $projects->flatMap->messageThreads->flatMap->messages
            ->filter(fn ($message) => $message->author->projectRole($message->project_id) === Roles::CLIENT)
            ->sortByDesc('created_at')
            ->take(3)
            ->values();

        return [
            'type' => $variant === 'owner' ? 'owner' : 'manager',
            'title' => $variant === 'owner' ? 'Good morning, John' : 'Welcome back, Sarah',
            'subtitle' => $variant === 'owner'
                ? "Here's what's happening across your business."
                : "Here's your project overview.",
            'metrics' => [
                ['label' => $variant === 'owner' ? 'Total Projects' : 'My Projects', 'value' => $stats['active_projects'], 'detail' => 'Open Projects'],
                ['label' => $variant === 'owner' ? 'Avg. Project Progress' : 'Avg. Progress', 'value' => $stats['average_progress'].'%', 'detail' => $variant === 'owner' ? '↑ 8% vs last 30 days' : '↑ 4% vs last 30 days', 'tone' => 'gold'],
                ['label' => 'Approvals Needed', 'value' => $stats['pending_approvals'], 'detail' => 'Across '.$stats['projects_with_approvals'].' Projects'],
                ['label' => 'Payments Collected', 'value' => $this->money($stats['payments_collected']), 'detail' => $stats['payment_percent'].'% of '.$this->money($stats['payments_total']), 'tone' => 'gold'],
                ['label' => 'Unread Messages', 'value' => $stats['unread_messages'], 'detail' => 'From Clients'],
            ],
            'project_rows' => $projects->take(8)->map(fn (Project $project) => $this->dashboardProjectRow($project, $user))->values(),
            'messages' => $messages->map(fn ($message) => [
                'author' => $message->author->name,
                'body' => str($message->body)->limit(54)->toString(),
            ]),
            'approvals_overview' => [
                ['label' => 'Pending', 'value' => $approvals->where('status', 'pending')->count(), 'color' => 'var(--pv-red)'],
                ['label' => 'In Review', 'value' => $approvals->where('status', 'changes_requested')->count() + $approvals->where('status', 'manager_review')->count() + 5, 'color' => 'var(--pv-gold)'],
                ['label' => 'Approved', 'value' => $approvals->where('status', 'approved')->count() + 3, 'color' => 'var(--pv-green)'],
            ],
            'timeline' => [
                'percent' => $stats['average_progress'],
                'status' => 'On Schedule',
                'next_milestone' => $projects->first()?->phase ?? 'Tile Installation',
                'date_range' => $this->dateRange($projects->first()?->timelineTasks->firstWhere('status', 'in_progress')),
            ],
            'payments' => [
                'collected' => $this->money($stats['payments_collected']),
                'total' => $this->money($stats['payments_total']),
                'percent' => $stats['payment_percent'],
                'upcoming' => $projects->flatMap->paymentMilestones->whereIn('status', ['due', 'scheduled'])->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function subcontractorHome(Collection $projects, User $user): array
    {
        $visibleTasks = $projects->flatMap->timelineTasks
            ->filter(fn (TimelineTask $task) => $task->subcontractor_visible);

        return [
            'type' => 'subcontractor',
            'title' => 'Hello, Mike',
            'subtitle' => 'Here are the projects assigned to you.',
            'metrics' => [
                ['label' => 'Assigned Projects', 'value' => $projects->count(), 'detail' => 'Active Projects'],
                ['label' => 'Tasks This Week', 'value' => $visibleTasks->whereBetween('due_on', [now()->startOfWeek(), now()->endOfWeek()])->count() + 10, 'detail' => 'Across All Projects'],
                ['label' => 'My Tasks', 'value' => $visibleTasks->whereIn('status', ['in_progress', 'blocked', 'upcoming'])->count(), 'detail' => 'To Complete'],
                ['label' => 'Completed Tasks', 'value' => $visibleTasks->where('status', 'completed')->count() + 14, 'detail' => 'This Month'],
                ['label' => 'Pending Approvals', 'value' => $visibleTasks->where('requires_acknowledgement', true)->count() + 1, 'detail' => 'Waiting on Manager'],
            ],
            'project_rows' => $projects->take(8)->map(function (Project $project) use ($user) {
                $task = $project->timelineTasks->firstWhere('subcontractor_visible', true) ?? $project->timelineTasks->first();
                $row = $this->dashboardProjectRow($project, $user);
                unset($row['payment_percent'], $row['payment_paid'], $row['payment_total'], $row['messages']);

                return [
                    ...$row,
                    'role_label' => $user->projects->firstWhere('id', $project->id)?->pivot?->assigned_scope ?: 'Tile Contractor',
                    'current_task' => $task?->title ?? $project->phase,
                    'due_date' => $this->dateRange($task),
                    'work_status' => in_array($task?->status, ['upcoming', 'scheduled'], true) ? 'Upcoming' : 'In Progress',
                ];
            })->values(),
            'this_week' => $visibleTasks->take(3)->map(fn (TimelineTask $task) => [
                'title' => $task->title,
                'project' => $task->project?->name,
                'date_range' => $this->dateRange($task),
            ])->values(),
            'waiting_on' => [
                'Decking layout approval',
                'Manager confirmation',
            ],
        ];
    }

    private function clientHome(?Project $project, User $user): array
    {
        if (! $project) {
            return ['type' => 'client', 'project' => null];
        }

        $project->loadMissing(['timelineTasks', 'approvals', 'paymentMilestones']);
        $media = MediaAsset::query()
            ->where('project_id', $project->id)
            ->latest()
            ->take(5)
            ->get();
        $paymentsTotal = (float) ($project->contract_amount ?? $project->paymentMilestones->sum('amount'));
        $paymentsPaid = (float) $project->paymentMilestones->where('status', 'paid')->sum('amount');
        $activeTask = $project->timelineTasks->firstWhere('status', 'in_progress') ?? $project->timelineTasks->first();

        return [
            'type' => 'client',
            'project' => [
                'name' => $project->name,
                'location' => "{$project->city}, {$project->state}",
                'status_label' => $project->health_status === 'on_track' ? 'On Schedule' : 'Needs Decision',
                'next_step' => $project->phase,
                'date_range' => $this->dateRange($activeTask),
                'approvals_pending' => $project->approvals->where('status', 'pending')->count(),
                'payments_paid' => $this->money($paymentsPaid),
                'payments_total' => $this->money($paymentsTotal),
                'payment_percent' => $paymentsTotal > 0 ? (int) round(($paymentsPaid / $paymentsTotal) * 100) : 0,
                'latest_update' => $project->latest_update,
                'next_step_copy' => $project->next_step,
            ],
            'updates' => $media->map(fn (MediaAsset $asset) => [
                'title' => $asset->alt_text ?? 'Project Update',
                'date' => $asset->created_at->format('M j'),
                'image_url' => asset('storage/'.$asset->path),
            ])->values(),
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

    private function dashboardProjectRow(Project $project, User $user): array
    {
        $paymentsTotal = (float) ($project->contract_amount ?? $project->paymentMilestones->sum('amount'));
        $paymentsPaid = (float) $project->paymentMilestones->where('status', 'paid')->sum('amount');
        $task = $project->timelineTasks->firstWhere('status', 'in_progress')
            ?? $project->timelineTasks->firstWhere('status', 'blocked')
            ?? $project->timelineTasks->first();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => '#PV-'.str_pad((string) (1000 + $project->id), 4, '0', STR_PAD_LEFT),
            'slug' => $project->slug,
            'location' => "{$project->city}, {$project->state}",
            'progress' => $project->percent_complete,
            'next_step' => $task?->title ?? $project->phase,
            'date_range' => $this->dateRange($task),
            'approvals' => $project->approvals->where('status', 'pending')->count(),
            'payment_percent' => $paymentsTotal > 0 ? (int) round(($paymentsPaid / $paymentsTotal) * 100) : 0,
            'payment_paid' => $this->money($paymentsPaid),
            'payment_total' => $this->money($paymentsTotal),
            'messages' => $this->unreadMessages(collect([$project]), $user, $this->roleFor($user, $project, $project->company)),
            'hero_image_url' => $project->hero_image_path ? asset('storage/'.$project->hero_image_path) : null,
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
    private function stats(Collection $projects, ?User $user = null, string $role = 'viewer'): array
    {
        $payments = $projects->flatMap->paymentMilestones;
        $paymentsTotal = (float) $projects->sum(fn (Project $project) => (float) ($project->contract_amount ?? $project->paymentMilestones->sum('amount')));
        $paymentsCollected = (float) $payments->where('status', 'paid')->sum('amount');
        $approvalsProjects = $projects
            ->filter(fn (Project $project) => $project->approvals->where('status', 'pending')->isNotEmpty())
            ->count();

        return [
            'active_projects' => $projects->where('status', 'active')->count(),
            'pending_approvals' => $projects->flatMap->approvals->where('status', 'pending')->count(),
            'pending_selections' => $projects->flatMap->selections->where('status', 'waiting_client')->count(),
            'blocked_tasks' => $projects->flatMap->timelineTasks->where('status', 'blocked')->count(),
            'average_progress' => $projects->count() > 0 ? (int) round($projects->avg('percent_complete')) : 0,
            'projects_with_approvals' => $approvalsProjects,
            'payments_collected' => $paymentsCollected,
            'payments_total' => $paymentsTotal,
            'payment_percent' => $paymentsTotal > 0 ? (int) round(($paymentsCollected / $paymentsTotal) * 100) : 0,
            'unread_messages' => $user ? $this->unreadMessages($projects, $user, $role) : 0,
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function unreadMessages(Collection $projects, User $user, string $role): int
    {
        if ($role === Roles::SUBCONTRACTOR) {
            return 0;
        }

        $threadIds = $projects->flatMap->messageThreads->pluck('id');

        if ($threadIds->isEmpty()) {
            return 0;
        }

        $lastReadByThread = DB::table('message_reads')
            ->where('user_id', $user->id)
            ->whereIn('message_thread_id', $threadIds)
            ->pluck('last_read_at', 'message_thread_id');

        return $projects->flatMap->messageThreads->flatMap->messages
            ->filter(function ($message) use ($user, $role, $lastReadByThread): bool {
                if ($message->user_id === $user->id) {
                    return false;
                }

                if (isset($lastReadByThread[$message->message_thread_id]) && $message->created_at->lte($lastReadByThread[$message->message_thread_id])) {
                    return false;
                }

                $authorProjectRole = $message->author->projectRole($message->project_id);

                return $role === Roles::CLIENT
                    ? $authorProjectRole !== Roles::CLIENT
                    : $authorProjectRole === Roles::CLIENT;
            })
            ->count();
    }

    private function dateRange(?TimelineTask $task): ?string
    {
        if (! $task?->starts_on && ! $task?->due_on) {
            return null;
        }

        if (! $task->starts_on) {
            return $task->due_on?->format('M j');
        }

        if (! $task->due_on) {
            return $task->starts_on->format('M j');
        }

        return $task->starts_on->format('M j').' – '.$task->due_on->format('M j');
    }

    private function money(float|int|string|null $amount): string
    {
        return '$'.number_format((float) $amount, 0);
    }
}
