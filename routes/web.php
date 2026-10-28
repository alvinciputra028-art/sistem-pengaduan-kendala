<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportCommentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportTriageController;
use App\Http\Controllers\ReportWorkflowController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('role:resident,staff,manager')
        ->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/queue', [ReportController::class, 'index'])
        ->middleware('role:staff,manager')
        ->name('reports.queue');
    Route::get('/reports/create', [ReportController::class, 'create'])
        ->middleware('role:resident')
        ->name('reports.create');
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('role:resident')
        ->name('reports.store');
    Route::post('/reports/{report}/comments', [ReportCommentController::class, 'store'])
        ->name('reports.comments.store');
    Route::post('/reports/{report}/close', [ReportWorkflowController::class, 'close'])
        ->name('reports.close');
    Route::post('/reports/{report}/reopen', [ReportWorkflowController::class, 'reopen'])
        ->name('reports.reopen');
    Route::get('/reports/{report}/triage', [ReportTriageController::class, 'edit'])
        ->name('reports.triage.edit');
    Route::patch('/reports/{report}/triage', [ReportTriageController::class, 'update'])
        ->name('reports.triage.update');
    Route::post('/reports/{report}/triage/status', [ReportTriageController::class, 'transition'])
        ->name('reports.triage.status');
    Route::post('/reports/{report}/assignments', [ReportTriageController::class, 'assign'])
        ->name('reports.assignments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])
        ->name('attachments.show');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
});
