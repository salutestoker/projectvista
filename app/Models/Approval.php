<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Approval extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'approval_template_id',
        'selection_id',
        'project_document_id',
        'requested_by_id',
        'responded_by_id',
        'title',
        'body',
        'status',
        'due_on',
        'responded_at',
        'response_note',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'responded_at' => 'datetime',
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

    public function selection(): BelongsTo
    {
        return $this->belongsTo(Selection::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }
}
