<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectVista;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectVista\StoreInvitationRequest;
use App\Models\Company;
use App\Models\Invitation;
use App\Support\ProjectVista\ProjectVistaData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyAdminController extends Controller
{
    public function show(Company $company, ProjectVistaData $data): Response
    {
        Gate::authorize('manageUsers', $company);

        return Inertia::render('ProjectVista/CompanyAdmin', $data->companyAdmin($company));
    }

    public function invite(StoreInvitationRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('manageUsers', $company);

        Invitation::query()->create([
            'company_id' => $company->id,
            'project_id' => $request->integer('project_id') ?: null,
            'invited_by_id' => $request->user()->id,
            'email' => $request->string('email')->toString(),
            'role' => $request->string('role')->toString(),
            'token' => Str::random(40),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('success', 'Invitation created for the demo workflow.');
    }
}
