<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTimelineTaskBlockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                'material',
                'inspection',
                'site',
                'administrative',
                'weather',
                'resource',
                'conflict',
            ])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
