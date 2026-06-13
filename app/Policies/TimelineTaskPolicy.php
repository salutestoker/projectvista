<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TimelineTask;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class TimelineTaskPolicy
{
    public function view(User $user, TimelineTask $timelineTask): bool
    {
        if ($user->isSuperAdmin() || in_array($user->companyRole($timelineTask->company_id), Roles::INTERNAL_ROLES, true)) {
            return true;
        }

        $projectRole = $timelineTask->project_id ? $user->projectRole($timelineTask->project_id) : null;

        return match ($projectRole) {
            Roles::CLIENT => $timelineTask->client_visible,
            Roles::SUBCONTRACTOR => $timelineTask->subcontractor_visible,
            default => false,
        };
    }

    public function update(User $user, TimelineTask $timelineTask): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($timelineTask->company_id), Roles::INTERNAL_ROLES, true);
    }
}
