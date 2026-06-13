<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MessageThread;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class MessageThreadPolicy
{
    public function view(User $user, MessageThread $messageThread): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($messageThread->company_id), Roles::INTERNAL_ROLES, true)
            || $user->projectRole($messageThread->project_id) === Roles::CLIENT;
    }

    public function createMessage(User $user, MessageThread $messageThread): bool
    {
        return $this->view($user, $messageThread);
    }
}
