<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use App\Support\ProjectVista\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = (int) $this->input('company_id');

        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'timeline_template_id' => [
                'required',
                'integer',
                Rule::exists('timeline_templates', 'id')->where('company_id', $companyId),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')
                    ->where('company_id', $companyId)
                    ->whereIn('role', Roles::INTERNAL_ROLES),
            ],
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email:rfc', 'max:255'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'contract_amount' => ['nullable', 'numeric', 'min:0'],
            'contract_signed_on' => ['required', 'date'],
            'client_summary' => ['nullable', 'string', 'max:4000'],
            'latest_update' => ['nullable', 'string', 'max:4000'],
            'next_step' => ['nullable', 'string', 'max:4000'],
            'subcontractor_ids' => ['array'],
            'subcontractor_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('company_user', 'user_id')
                    ->where('company_id', $companyId)
                    ->where('role', Roles::SUBCONTRACTOR),
            ],
        ];
    }
}
