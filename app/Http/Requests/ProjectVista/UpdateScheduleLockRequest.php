<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateScheduleLockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_schedule_locked' => ['required', 'boolean'],
            'schedule_locked_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
