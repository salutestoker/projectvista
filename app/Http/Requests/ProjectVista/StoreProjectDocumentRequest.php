<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
        ];
    }
}
