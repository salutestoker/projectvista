<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use App\Support\ProjectVista\Roles;
use App\Support\ProjectVista\TimelineScheduler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTimelineTaskRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->route('project')->company_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'phase' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(TimelineScheduler::STATUSES)],
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
            'client_visible' => ['boolean'],
            'subcontractor_visible' => ['boolean'],
            'requires_acknowledgement' => ['boolean'],
            'acknowledge_conflicts' => ['boolean'],
        ];
    }
}
