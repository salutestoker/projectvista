<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->companies()->exists();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isSuperAdmin() || $user->belongsToCompany($company);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isSuperAdmin()
            || $user->companyRole($company) === Roles::COMPANY_ADMIN;
    }

    public function manageUsers(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }

    public function manageTemplates(User $user, Company $company): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($company), Roles::INTERNAL_ROLES, true);
    }
}
