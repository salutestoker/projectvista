<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:255'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'contract_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'estimated_completion_on' => ['nullable', 'date'],
            'project_type' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', 'max:80'],
            'phase' => ['required', 'string', 'max:120'],
        ];
    }
}
