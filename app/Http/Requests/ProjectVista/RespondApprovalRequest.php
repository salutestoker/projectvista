<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RespondApprovalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'changes_requested'])],
            'response_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
