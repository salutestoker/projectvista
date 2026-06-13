<?php

declare(strict_types=1);

namespace App\Support\ProjectVista;

final class Roles
{
    public const COMPANY_ADMIN = 'company_admin';

    public const COMPANY_MANAGER = 'company_manager';

    public const SUBCONTRACTOR = 'subcontractor';

    public const CLIENT = 'client';

    public const PROJECT_VIEWER = 'viewer';

    public const INTERNAL_ROLES = [
        self::COMPANY_ADMIN,
        self::COMPANY_MANAGER,
    ];
}
