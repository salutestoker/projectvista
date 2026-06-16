<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use App\Support\ProjectVista\Roles;
use App\Support\ProjectVista\TimelineScheduler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewTimelineTaskRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->route('project')->company_id;

        return [
            'status' => ['sometimes', 'required', Rule::in(TimelineScheduler::STATUSES)],
            'duration_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
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
            'is_job_site_ready' => ['boolean'],
            'are_materials_ready' => ['boolean'],
            'is_customer_approval_required' => ['boolean'],
            'is_customer_approval_received' => ['boolean'],
        ];
    }
}
