<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTimelineTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->route('company')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['boolean'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.id' => ['nullable', 'integer'],
            'tasks.*.name' => ['required', 'string', 'max:255'],
            'tasks.*.phase' => ['nullable', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:2000'],
            'tasks.*.sequence_order' => ['required', 'integer', 'min:1'],
            'tasks.*.default_duration_working_days' => ['required', 'integer', 'min:1', 'max:365'],
            'tasks.*.uses_calendar_days' => ['boolean'],
            'tasks.*.is_system' => ['boolean'],
            'tasks.*.default_subcontractor_type_id' => [
                'nullable',
                'integer',
                Rule::exists('subcontractor_types', 'id')->where('company_id', $companyId),
            ],
            'tasks.*.internal_only' => ['boolean'],
        ];
    }
}
