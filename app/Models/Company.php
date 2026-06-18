<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

final class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'subscription_status',
        'brand_primary_color',
        'brand_accent_color',
        'logo_path',
        'feature_flags',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'feature_flags' => 'array',
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot($this->companyUserPivotColumns())
            ->withTimestamps();
    }

    public function subcontractorTypes(): HasMany
    {
        return $this->hasMany(SubcontractorType::class)->orderBy('sort_order');
    }

    public function timelineTemplates(): HasMany
    {
        return $this->hasMany(TimelineTemplate::class);
    }

    public function timelineTaskTemplates(): HasMany
    {
        return $this->hasMany(TimelineTaskTemplate::class)->orderBy('sequence_order');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scheduleRuns(): HasMany
    {
        return $this->hasMany(ScheduleRun::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return list<string>
     */
    private function companyUserPivotColumns(): array
    {
        $columns = [
            'role',
            'title',
            'invited_at',
            'joined_at',
        ];

        foreach ([
            'subcontractor_type_id',
            'scheduling_capacity_daily',
            'reliability_score',
            'scheduling_is_active',
        ] as $column) {
            if (Schema::hasColumn('company_user', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }
}
