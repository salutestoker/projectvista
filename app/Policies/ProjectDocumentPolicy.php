<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProjectDocument;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class ProjectDocumentPolicy
{
    public function view(User $user, ProjectDocument $projectDocument): bool
    {
        if ($user->isSuperAdmin() || in_array($user->companyRole($projectDocument->company_id), Roles::INTERNAL_ROLES, true)) {
            return true;
        }

        return match ($user->projectRole($projectDocument->project_id)) {
            Roles::CLIENT => $projectDocument->client_visible,
            Roles::SUBCONTRACTOR => $projectDocument->subcontractor_visible,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->companies()->wherePivotIn('role', Roles::INTERNAL_ROLES)->exists();
    }

    public function update(User $user, ProjectDocument $projectDocument): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($projectDocument->company_id), Roles::INTERNAL_ROLES, true);
    }
}
