<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderTimelineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.id' => ['required', 'integer', 'exists:timeline_tasks,id'],
            'tasks.*.sort_order' => ['required', 'integer', 'min:1'],
        ];
    }
}
