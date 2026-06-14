<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use App\Support\ProjectVista\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->route('company')->id;

        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in([Roles::COMPANY_MANAGER, Roles::SUBCONTRACTOR, Roles::CLIENT])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'subcontractor_type_id' => [
                'nullable',
                'required_if:role,'.Roles::SUBCONTRACTOR,
                'integer',
                Rule::exists('subcontractor_types', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
