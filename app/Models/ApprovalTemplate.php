<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ApprovalTemplate extends Model
{
    protected $fillable = ['company_id', 'title', 'description', 'default_due_days'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
