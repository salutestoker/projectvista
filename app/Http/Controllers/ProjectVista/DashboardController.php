<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectVista;

use App\Http\Controllers\Controller;
use App\Support\ProjectVista\ProjectVistaData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, ProjectVistaData $data): Response
    {
        return Inertia::render('ProjectVista/Dashboard', $data->dashboard($request->user()));
    }
}
