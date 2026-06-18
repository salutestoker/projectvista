<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use App\Support\ProjectVista\Roles;
use App\Support\ProjectVista\TimelineScheduler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTimelineTaskRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->route('project')->company_id;

        return [
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where('company_id', $companyId),
            ],
            'predecessor_task_id' => [
                'required',
                'integer',
                Rule::exists('timeline_tasks', 'id')->where('company_id', $companyId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'phase' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(TimelineScheduler::STATUSES)],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:4'],
            'customer_urgency' => ['nullable', 'integer', 'min:0', 'max:4'],
            'is_schedule_locked' => ['boolean'],
            'schedule_locked_reason' => ['nullable', 'string', 'max:255'],
            'assigned_subcontractor_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')
                    ->where('company_id', $companyId)
                    ->where('role', Roles::SUBCONTRACTOR),
            ],
            'subcontractor_type_id' => [
                'nullable',
                'integer',
                Rule::exists('subcontractor_types', 'id')->where('company_id', $companyId),
            ],
            'internal_only' => ['boolean'],
            'requires_acknowledgement' => ['boolean'],
            'is_job_site_ready' => ['boolean'],
            'are_materials_ready' => ['boolean'],
            'is_customer_approval_required' => ['boolean'],
            'is_customer_approval_received' => ['boolean'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
            'customer_notes' => ['nullable', 'string', 'max:4000'],
            'acknowledge_conflicts' => ['boolean'],
        ];
    }
}
