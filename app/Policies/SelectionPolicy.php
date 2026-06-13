<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Selection;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class SelectionPolicy
{
    public function view(User $user, Selection $selection): bool
    {
        if ($user->isSuperAdmin() || in_array($user->companyRole($selection->company_id), Roles::INTERNAL_ROLES, true)) {
            return true;
        }

        return match ($user->projectRole($selection->project_id)) {
            Roles::CLIENT => true,
            Roles::SUBCONTRACTOR => $selection->status === 'approved',
            default => false,
        };
    }

    public function update(User $user, Selection $selection): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($selection->company_id), Roles::INTERNAL_ROLES, true);
    }

    public function respond(User $user, Selection $selection): bool
    {
        return $user->projectRole($selection->project_id) === Roles::CLIENT;
    }
}
