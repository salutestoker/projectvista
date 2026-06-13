<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class ApprovalPolicy
{
    public function view(User $user, Approval $approval): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($approval->company_id), Roles::INTERNAL_ROLES, true)
            || $user->projectRole($approval->project_id) === Roles::CLIENT;
    }

    public function update(User $user, Approval $approval): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($approval->company_id), Roles::INTERNAL_ROLES, true);
    }

    public function respond(User $user, Approval $approval): bool
    {
        return $approval->status === 'pending'
            && $user->projectRole($approval->project_id) === Roles::CLIENT;
    }
}
