<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreSubcontractorTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $slugSource = filled($this->input('slug'))
            ? (string) $this->input('slug')
            : (string) $this->input('name');

        $this->merge([
            'slug' => Str::slug($slugSource),
        ]);
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id;
        $subcontractorType = $this->route('subcontractorType');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subcontractor_types', 'slug')
                    ->where('company_id', $companyId)
                    ->ignore($subcontractorType?->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'allows_same_project_overlap' => ['required', 'boolean'],
        ];
    }
}
