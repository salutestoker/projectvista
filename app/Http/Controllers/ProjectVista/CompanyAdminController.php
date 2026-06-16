<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectVista;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectVista\StoreInvitationRequest;
use App\Http\Requests\ProjectVista\StoreTimelineTemplateRequest;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\TimelineTaskTemplate;
use App\Models\TimelineTemplate;
use App\Services\Scheduling\ProjectTimelineScheduler;
use App\Support\ProjectVista\ProjectVistaData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyAdminController extends Controller
{
    public function show(Company $company, ProjectVistaData $data): Response
    {
        Gate::authorize('view', $company);

        return Inertia::render('ProjectVista/CompanyAdmin', $data->companyAdmin($company));
    }

    public function invite(StoreInvitationRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('manageUsers', $company);

        Invitation::query()->create([
            'company_id' => $company->id,
            'project_id' => $request->integer('project_id') ?: null,
            'invited_by_id' => $request->user()->id,
            'email' => $request->string('email')->toString(),
            'role' => $request->string('role')->toString(),
            'subcontractor_type_id' => $request->integer('subcontractor_type_id') ?: null,
            'token' => Str::random(40),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('success', 'Invitation created for the demo workflow.');
    }

    public function storeTimelineTemplate(
        StoreTimelineTemplateRequest $request,
        Company $company,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('manageTemplates', $company);

        $data = $request->validated();

        DB::transaction(function () use ($company, $data, $projectTimelineScheduler): void {
            if ((bool) ($data['is_default'] ?? false)) {
                $company->timelineTemplates()->update(['is_default' => false]);
            }

            $template = TimelineTemplate::query()->create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            $this->syncTimelineTemplateTasks($template, $data['tasks'], $projectTimelineScheduler);
        });

        return back()->with('success', 'Timeline template created.');
    }

    public function updateTimelineTemplate(
        StoreTimelineTemplateRequest $request,
        Company $company,
        TimelineTemplate $timelineTemplate,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): RedirectResponse {
        Gate::authorize('manageTemplates', $company);
        abort_unless($timelineTemplate->company_id === $company->id, 404);

        $data = $request->validated();

        DB::transaction(function () use ($company, $timelineTemplate, $data, $projectTimelineScheduler): void {
            if ((bool) ($data['is_default'] ?? false)) {
                $company->timelineTemplates()
                    ->whereKeyNot($timelineTemplate->id)
                    ->update(['is_default' => false]);
            }

            $timelineTemplate->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            $this->syncTimelineTemplateTasks($timelineTemplate, $data['tasks'], $projectTimelineScheduler);
        });

        return back()->with('success', 'Timeline template saved.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    private function syncTimelineTemplateTasks(
        TimelineTemplate $timelineTemplate,
        array $tasks,
        ProjectTimelineScheduler $projectTimelineScheduler,
    ): void
    {
        $systemTask = $projectTimelineScheduler->ensureContractSignedTemplateTask($timelineTemplate);
        $editableTasks = collect($tasks)
            ->reject(fn (array $task): bool => (bool) ($task['is_system'] ?? false)
                || (isset($task['id']) && (int) $task['id'] === $systemTask->id)
                || Str::lower((string) ($task['name'] ?? '')) === 'contract signed')
            ->values();
        $existingIds = $timelineTemplate->taskTemplates()
            ->whereKeyNot($systemTask->id)
            ->pluck('id');
        $submittedIds = $editableTasks
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        abort_if($submittedIds->diff($existingIds)->isNotEmpty(), 422, 'Invalid timeline task template.');

        $timelineTemplate->taskTemplates()
            ->whereKeyNot($systemTask->id)
            ->whereNotIn('id', $submittedIds)
            ->delete();

        $timelineTemplate->taskTemplates()
            ->whereKeyNot($systemTask->id)
            ->whereIn('id', $submittedIds)
            ->get()
            ->each(function (TimelineTaskTemplate $taskTemplate, int $index): void {
                $taskTemplate->update(['sequence_order' => 10000 + $index]);
            });

        foreach ($editableTasks->values() as $index => $task) {
            $attributes = [
                'company_id' => $timelineTemplate->company_id,
                'timeline_template_id' => $timelineTemplate->id,
                'default_subcontractor_type_id' => $task['default_subcontractor_type_id'] ?? null,
                'name' => $task['name'],
                'description' => $task['description'] ?? null,
                'sequence_order' => $index + 2,
                'default_duration_working_days' => $task['default_duration_working_days'],
                'internal_only' => (bool) ($task['internal_only'] ?? false),
                'is_system' => false,
            ];

            if (! empty($task['id'])) {
                TimelineTaskTemplate::query()
                    ->where('timeline_template_id', $timelineTemplate->id)
                    ->whereKey((int) $task['id'])
                    ->update($attributes);

                continue;
            }

            TimelineTaskTemplate::query()->create($attributes);
        }

        $projectTimelineScheduler->ensureContractSignedTemplateTask($timelineTemplate);
    }
}
