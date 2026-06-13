<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectDocument extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'uploaded_by_id',
        'title',
        'category',
        'disk',
        'path',
        'mime_type',
        'size',
        'version',
        'visibility',
        'client_visible',
        'subcontractor_visible',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'client_visible' => 'boolean',
            'subcontractor_visible' => 'boolean',
            'version' => 'integer',
            'size' => 'integer',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
