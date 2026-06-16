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
            Roles::CLIENT => ! $timelineTask->internal_only,
            Roles::SUBCONTRACTOR => $timelineTask->assigned_subcontractor_id === $user->id,
            default => false,
        };
    }

    public function update(User $user, TimelineTask $timelineTask): bool
    {
        return ! $timelineTask->is_system
            && ($user->isSuperAdmin()
                || in_array($user->companyRole($timelineTask->company_id), Roles::INTERNAL_ROLES, true));
    }

    public function delete(User $user, TimelineTask $timelineTask): bool
    {
        return $this->update($user, $timelineTask);
    }

    public function rescheduleTask(User $user, TimelineTask $timelineTask): bool
    {
        return $this->update($user, $timelineTask);
    }

    public function previewConflicts(User $user, TimelineTask $timelineTask): bool
    {
        return $this->update($user, $timelineTask);
    }

    public function viewInternalConflicts(User $user, TimelineTask $timelineTask): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($timelineTask->company_id), Roles::INTERNAL_ROLES, true);
    }

    public function overrideScheduleConflict(User $user, TimelineTask $timelineTask): bool
    {
        return false;
    }
}
