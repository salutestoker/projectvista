<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\ProjectVista\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_super_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'title', 'subcontractor_type_id', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot(['role', 'assigned_scope', 'permissions'])
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function companyRole(Company|int $company): ?string
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        $membership = $this->companies->firstWhere('id', $companyId)
            ?? $this->companies()->whereKey($companyId)->first();

        return $membership?->pivot?->role;
    }

    public function projectRole(Project|int $project): ?string
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        $assignment = $this->projects->firstWhere('id', $projectId)
            ?? $this->projects()->whereKey($projectId)->first();

        return $assignment?->pivot?->role;
    }

    public function belongsToCompany(Company|int $company): bool
    {
        return $this->isSuperAdmin() || $this->companyRole($company) !== null;
    }

    public function canManageCompany(Company|int $company): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($this->companyRole($company), [
            Roles::COMPANY_ADMIN,
            Roles::COMPANY_MANAGER,
        ], true);
    }
}
