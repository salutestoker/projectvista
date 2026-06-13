<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectVista;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use App\Support\ProjectVista\ProjectVistaData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SuperAdminController extends Controller
{
    public function __invoke(Request $request, ProjectVistaData $data): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        Gate::authorize('viewAny', Company::class);

        return Inertia::render('ProjectVista/SuperAdmin', [
            ...$data->dashboard($request->user()),
            'platform' => [
                'companies_count' => Company::query()->count(),
                'projects_count' => Project::query()->count(),
                'active_trials' => Company::query()->where('subscription_status', 'trial')->count(),
            ],
        ]);
    }
}
