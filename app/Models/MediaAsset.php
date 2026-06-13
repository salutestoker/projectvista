<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MediaAsset extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'uploaded_by_id',
        'collection',
        'kind',
        'disk',
        'path',
        'alt_text',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
