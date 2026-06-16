<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->companies()->exists()
            || $user->projects()->exists();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isSuperAdmin()
            || $user->belongsToCompany($project->company_id)
            || $user->projectRole($project) !== null;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->companies()->wherePivotIn('role', Roles::INTERNAL_ROLES)->exists();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($project->company_id), Roles::INTERNAL_ROLES, true)
            || $user->projectRole($project) === Roles::COMPANY_MANAGER;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($project->company_id), Roles::INTERNAL_ROLES, true)
            || $user->projectRole($project) === Roles::COMPANY_MANAGER;
    }
}
