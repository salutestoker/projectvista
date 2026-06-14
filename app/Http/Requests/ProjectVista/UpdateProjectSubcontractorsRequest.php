<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectSubcontractorsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subcontractor_ids' => ['array'],
            'subcontractor_ids.*' => ['integer'],
        ];
    }
}
