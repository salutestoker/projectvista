<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectVista\CompanyAdminController;
use App\Http\Controllers\ProjectVista\DashboardController;
use App\Http\Controllers\ProjectVista\ProjectController;
use App\Http\Controllers\ProjectVista\SuperAdminController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/admin/command-center', SuperAdminController::class)->name('super-admin.dashboard');
    Route::get('/admin/components', function () {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        return Inertia::render('ProjectVista/ComponentLibrary');
    })->name('super-admin.components');

    Route::get('/companies/{company:slug}/admin', [CompanyAdminController::class, 'show'])->name('companies.admin');
    Route::post('/companies/{company:slug}/invitations', [CompanyAdminController::class, 'invite'])->name('companies.invitations.store');
    Route::post('/companies/{company:slug}/timeline-templates', [CompanyAdminController::class, 'storeTimelineTemplate'])->name('companies.timeline-templates.store');
    Route::patch('/companies/{company:slug}/timeline-templates/{timelineTemplate}', [CompanyAdminController::class, 'updateTimelineTemplate'])->name('companies.timeline-templates.update');

    Route::get('/storage/project-documents/{projectId}/{filename}', [ProjectController::class, 'showDocumentFromStoragePath'])
        ->whereNumber('projectId')
        ->where('filename', '[^/]+')
        ->name('projects.documents.storage');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/timelines', [ProjectController::class, 'timelines'])->name('timelines.index');
    Route::get('/projects/new', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('/projects/{project:slug}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project:slug}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/projects/{project:slug}/media', [ProjectController::class, 'storeMedia'])->name('projects.media.store');
    Route::get('/projects/{project:slug}/media/{mediaAsset}', [ProjectController::class, 'showMedia'])->name('projects.media.show');
    Route::patch('/projects/{project:slug}/subcontractors', [ProjectController::class, 'updateSubcontractors'])->name('projects.subcontractors.update');
    Route::get('/projects/{project:slug}/timeline', [ProjectController::class, 'timeline'])->name('projects.timeline');
    Route::patch('/projects/{project:slug}/timeline/reorder', [ProjectController::class, 'reorderTimeline'])->name('projects.timeline.reorder');
    Route::post('/projects/{project:slug}/timeline/tasks', [ProjectController::class, 'storeTimelineTask'])->name('projects.timeline.tasks.store');
    Route::post('/projects/{project:slug}/timeline/tasks/{task}/preview', [ProjectController::class, 'previewTimelineTask'])->name('projects.timeline.tasks.preview');
    Route::patch('/projects/{project:slug}/timeline/tasks/{task}', [ProjectController::class, 'updateTimelineTask'])->name('projects.timeline.tasks.update');
    Route::delete('/projects/{project:slug}/timeline/tasks/{task}', [ProjectController::class, 'destroyTimelineTask'])->name('projects.timeline.tasks.destroy');
    Route::get('/projects/{project:slug}/selections', [ProjectController::class, 'selections'])->name('projects.selections');
    Route::get('/projects/{project:slug}/approvals', [ProjectController::class, 'approvals'])->name('projects.approvals');
    Route::get('/projects/{project:slug}/payments', [ProjectController::class, 'payments'])->name('projects.payments');
    Route::get('/projects/{project:slug}/documents', [ProjectController::class, 'documents'])->name('projects.documents');
    Route::post('/projects/{project:slug}/documents', [ProjectController::class, 'storeDocument'])->name('projects.documents.store');
    Route::get('/projects/{project:slug}/documents/{document}', [ProjectController::class, 'showDocument'])->name('projects.documents.show');
    Route::get('/projects/{project:slug}/messages', [ProjectController::class, 'messages'])->name('projects.messages');

    Route::patch('/selections/{selection}/response', [ProjectController::class, 'respondSelection'])->name('selections.response');
    Route::patch('/approvals/{approval}/response', [ProjectController::class, 'respondApproval'])->name('approvals.response');
    Route::patch('/payment-milestones/{paymentMilestone}/complete', [ProjectController::class, 'completePayment'])->name('payment-milestones.complete');
    Route::post('/message-threads/{thread}/messages', [ProjectController::class, 'storeMessage'])->name('message-threads.messages.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
