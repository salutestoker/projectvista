<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RespondSelectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'changes_requested'])],
            'client_response' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
