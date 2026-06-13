<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SelectionCategory extends Model
{
    protected $fillable = ['company_id', 'name', 'description', 'sort_order'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(Selection::class);
    }
}
