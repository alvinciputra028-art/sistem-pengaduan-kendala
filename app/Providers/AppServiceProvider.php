<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Report;
use App\Models\ReportComment;
use App\Models\Notification;
use App\Policies\AttachmentPolicy;
use App\Policies\ReportCommentPolicy;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(ReportComment::class, ReportCommentPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);

        View::composer('layouts.app', function ($view): void {
            $view->with('unreadNotificationCount', auth()->check()
                ? Notification::query()
                    ->where('user_id', auth()->id())
                    ->whereNull('read_at')
                    ->count()
                : 0);
        });
    }
}
