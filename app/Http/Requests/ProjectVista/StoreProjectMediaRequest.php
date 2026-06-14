<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,heic,webp', 'max:25600'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
