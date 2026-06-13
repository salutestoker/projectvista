<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class MediaAssetPolicy
{
    public function view(User $user, MediaAsset $mediaAsset): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($mediaAsset->company_id), Roles::INTERNAL_ROLES, true)
            || ($mediaAsset->project_id !== null && $user->projectRole($mediaAsset->project_id) !== null);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->companies()->wherePivotIn('role', Roles::INTERNAL_ROLES)->exists();
    }
}
