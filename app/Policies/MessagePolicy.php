<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\ProjectVista\Roles;

final class MessagePolicy
{
    public function viewThread(User $user, MessageThread $thread): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($thread->company_id), Roles::INTERNAL_ROLES, true)
            || $user->projectRole($thread->project_id) === Roles::CLIENT;
    }

    public function createInThread(User $user, MessageThread $thread): bool
    {
        return $this->viewThread($user, $thread);
    }

    public function view(User $user, Message $message): bool
    {
        return $user->isSuperAdmin()
            || in_array($user->companyRole($message->company_id), Roles::INTERNAL_ROLES, true)
            || $user->projectRole($message->project_id) === Roles::CLIENT;
    }
}
