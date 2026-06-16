<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Company;
use App\Models\SubcontractorType;
use App\Models\TimelineTaskTemplate;
use App\Models\TimelineTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TimelineTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_manager_can_create_timeline_template_from_copied_rows(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $sourceTemplate = TimelineTemplate::query()
            ->where('company_id', $company->id)
            ->with('taskTemplates')
            ->firstOrFail();

        $this->actingAs($manager)
            ->post(route('companies.timeline-templates.store', $company), $this->templatePayload(
                $sourceTemplate,
                name: 'Manager Copied Timeline',
                clearIds: true,
            ))
            ->assertRedirect();

        $template = TimelineTemplate::query()
            ->where('company_id', $company->id)
            ->where('name', 'Manager Copied Timeline')
            ->firstOrFail();

        $this->assertFalse($template->is_default);
        $this->assertSame($sourceTemplate->taskTemplates->count(), $template->taskTemplates()->count());
        $firstTask = $template->taskTemplates()->orderBy('sequence_order')->firstOrFail();
        $this->assertSame('Contract Signed', $firstTask->name);
        $this->assertTrue($firstTask->is_system);
    }

    public function test_company_manager_can_update_reorder_add_and_delete_template_rows(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $template = TimelineTemplate::query()
            ->where('company_id', $company->id)
            ->with('taskTemplates')
            ->firstOrFail();
        $tasks = $template->taskTemplates()->orderBy('sequence_order')->take(2)->get();

        $this->actingAs($manager)
            ->patch(route('companies.timeline-templates.update', [$company, $template]), [
                'name' => 'Edited Pool Timeline',
                'description' => 'Updated from the editable template table.',
                'is_default' => true,
                'tasks' => [
                    $this->taskPayload($tasks[1], [
                        'name' => 'Permitting First',
                        'sequence_order' => 1,
                    ]),
                    $this->taskPayload($tasks[0], [
                        'name' => 'Contract Second',
                        'sequence_order' => 2,
                    ]),
                    [
                        'id' => null,
                        'name' => 'Owner Orientation',
                        'description' => 'Final homeowner walkthrough.',
                        'sequence_order' => 3,
                        'default_duration_working_days' => 1,
                        'default_subcontractor_type_id' => null,
                        'internal_only' => true,
                    ],
                ],
            ])
            ->assertRedirect();

        $template->refresh();
        $orderedTasks = $template->taskTemplates()->orderBy('sequence_order')->get();

        $this->assertSame('Edited Pool Timeline', $template->name);
        $this->assertTrue($template->is_default);
        $this->assertCount(3, $orderedTasks);
        $this->assertSame('Contract Signed', $orderedTasks[0]->name);
        $this->assertSame($tasks[0]->id, $orderedTasks[0]->id);
        $this->assertTrue($orderedTasks[0]->is_system);
        $this->assertSame('Permitting First', $orderedTasks[1]->name);
        $this->assertSame($tasks[1]->id, $orderedTasks[1]->id);
        $this->assertSame('Owner Orientation', $orderedTasks[2]->name);
    }

    public function test_template_management_rejects_cross_company_users_and_trade_types(): void
    {
        $this->seed();

        $otherAdmin = User::query()->where('email', 'admin@desertstone.test')->firstOrFail();
        $company = Company::query()->where('slug', 'omni-pool-builders')->firstOrFail();
        $template = TimelineTemplate::query()->where('company_id', $company->id)->firstOrFail();
        $otherType = SubcontractorType::query()
            ->where('company_id', '!=', $company->id)
            ->firstOrFail();
        $admin = User::query()->where('email', 'admin@omnipools.test')->firstOrFail();

        $this->actingAs($otherAdmin)
            ->patch(route('companies.timeline-templates.update', [$company, $template]), $this->templatePayload($template))
            ->assertForbidden();

        $payload = $this->templatePayload($template, name: 'Invalid Trade Timeline');
        $payload['tasks'][0]['default_subcontractor_type_id'] = $otherType->id;

        $this->actingAs($admin)
            ->post(route('companies.timeline-templates.store', $company), $payload)
            ->assertSessionHasErrors('tasks.0.default_subcontractor_type_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(
        TimelineTemplate $template,
        string $name = 'Updated Timeline',
        bool $clearIds = false,
    ): array {
        $template->loadMissing('taskTemplates');

        return [
            'name' => $name,
            'description' => $template->description,
            'is_default' => false,
            'tasks' => $template->taskTemplates
                ->sortBy('sequence_order')
                ->values()
                ->map(fn (TimelineTaskTemplate $taskTemplate) => $this->taskPayload($taskTemplate, [
                    'id' => $clearIds ? null : $taskTemplate->id,
                ]))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function taskPayload(TimelineTaskTemplate $taskTemplate, array $overrides = []): array
    {
        return [
            'id' => $taskTemplate->id,
            'name' => $taskTemplate->name,
            'description' => $taskTemplate->description,
            'sequence_order' => $taskTemplate->sequence_order,
            'default_duration_working_days' => $taskTemplate->default_duration_working_days,
            'default_subcontractor_type_id' => $taskTemplate->default_subcontractor_type_id,
            'internal_only' => $taskTemplate->internal_only,
            'is_system' => $taskTemplate->is_system,
            ...$overrides,
        ];
    }
}
