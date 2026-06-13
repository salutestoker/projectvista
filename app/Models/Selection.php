<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Selection extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'selection_category_id',
        'approved_by',
        'name',
        'description',
        'image_path',
        'status',
        'manager_note',
        'client_response',
        'due_on',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SelectionCategory::class, 'selection_category_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
