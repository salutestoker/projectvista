<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSubcontractorSchedulingRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'subcontractor_type_id' => [
                'nullable',
                'integer',
                Rule::exists('subcontractor_types', 'id')->where('company_id', $companyId),
            ],
            'scheduling_capacity_daily' => ['required', 'integer', 'min:1', 'max:20'],
            'reliability_score' => ['required', 'integer', 'min:0', 'max:100'],
            'scheduling_is_active' => ['required', 'boolean'],
        ];
    }
}
