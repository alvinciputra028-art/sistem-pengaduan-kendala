<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportCommentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportTriageController;
use App\Http\Controllers\ReportWorkflowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CommunityController;
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
    Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
    Route::get('/community/moderation', [CommunityController::class, 'moderation'])
        ->middleware('role:manager')
        ->name('community.moderation');
    Route::post('/community/{report}/acknowledge', [CommunityController::class, 'acknowledge'])->name('community.acknowledge');
    Route::post('/community/{report}/report', [CommunityController::class, 'report'])->name('community.report');
    Route::post('/community/{report}/duplicate', [CommunityController::class, 'duplicate'])->name('community.duplicate');
    Route::post('/community/{report}/hide', [CommunityController::class, 'hide'])->name('community.hide');
    Route::post('/community/{report}/restore', [CommunityController::class, 'restore'])->name('community.restore');
    Route::post('/community/{report}/cancel-duplicate', [CommunityController::class, 'cancelDuplicate'])->name('community.cancel-duplicate');
    Route::post('/community/{report}/review', [CommunityController::class, 'review'])->name('community.review');
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
        ->middleware('throttle:10,1')
        ->name('reports.comments.store');
    Route::post('/comments/{comment}/report', [ReportCommentController::class, 'report'])
        ->middleware('throttle:5,1')
        ->name('comments.report');
    Route::post('/comments/{comment}/hide', [ReportCommentController::class, 'hide'])
        ->name('comments.hide');
    Route::post('/comments/{comment}/restore', [ReportCommentController::class, 'restore'])
        ->name('comments.restore');
    Route::post('/reports/{report}/community-chat/disable', [ReportCommentController::class, 'disableConversation'])
        ->name('reports.community-chat.disable');
    Route::post('/reports/{report}/community-chat/enable', [ReportCommentController::class, 'enableConversation'])
        ->name('reports.community-chat.enable');
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
