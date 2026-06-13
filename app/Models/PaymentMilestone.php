<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentMilestone extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'label',
        'description',
        'amount',
        'status',
        'due_on',
        'completed_on',
        'payment_url',
        'provider_label',
        'client_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_on' => 'date',
            'completed_on' => 'date',
            'client_visible' => 'boolean',
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
}
