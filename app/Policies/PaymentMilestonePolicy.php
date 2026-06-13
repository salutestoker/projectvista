<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentMilestone;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class PaymentMilestonePolicy
{
    public function view(User $user, PaymentMilestone $paymentMilestone): bool
    {
        if ($user->isSuperAdmin() || in_array($user->companyRole($paymentMilestone->company_id), Roles::INTERNAL_ROLES, true)) {
            return true;
        }

        return $paymentMilestone->client_visible
            && $user->projectRole($paymentMilestone->project_id) === Roles::CLIENT;
    }

    public function update(User $user, PaymentMilestone $paymentMilestone): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($paymentMilestone->company_id), Roles::INTERNAL_ROLES, true);
    }
}
