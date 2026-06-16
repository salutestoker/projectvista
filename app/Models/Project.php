<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'manager_id',
        'name',
        'slug',
        'address_line',
        'city',
        'state',
        'postal_code',
        'client_name',
        'client_email',
        'percent_complete',
        'health_status',
        'contract_amount',
        'contract_signed_on',
        'hero_image_path',
        'client_summary',
        'latest_update',
        'next_step',
    ];

    protected function casts(): array
    {
        return [
            'contract_amount' => 'decimal:2',
            'contract_signed_on' => 'date',
            'percent_complete' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->whereIn('company_id', $user->companies()->select('companies.id'))
                ->orWhereIn('id', $user->projects()->select('projects.id'));
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'assigned_scope', 'permissions'])
            ->withTimestamps();
    }

    public function timelineTasks(): HasMany
    {
        return $this->hasMany(TimelineTask::class)
            ->orderByRaw('COALESCE(sequence_order, sort_order)')
            ->orderBy('sort_order');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(Selection::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function paymentMilestones(): HasMany
    {
        return $this->hasMany(PaymentMilestone::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class);
    }
}
