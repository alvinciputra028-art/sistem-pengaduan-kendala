<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reports:notify-overdue', function (): void {
    $reports = Report::query()
        ->with(['reporter', 'assignments' => fn ($query) => $query->whereNull('unassigned_at')])
        ->whereNotIn('status', ['closed'])
        ->where(function ($query): void {
            $query->where('estimated_response_at', '<', now())
                ->whereIn('status', ['submitted', 'triaged', 'assigned'])
                ->orWhere(function ($query): void {
                    $query->where('estimated_resolution_at', '<', now())
                        ->whereNotIn('status', ['waiting_confirmation', 'closed']);
                });
        })
        ->get();

    $managers = User::query()->where('role', 'manager')->where('is_active', true)->pluck('id');
    $created = 0;
    foreach ($reports as $report) {
        $deadlines = [];
        if ($report->estimated_response_at?->isPast() && in_array($report->status, ['submitted', 'triaged', 'assigned'], true)) {
            $deadlines[] = 'response';
        }
        if ($report->estimated_resolution_at?->isPast() && ! in_array($report->status, ['waiting_confirmation', 'closed'], true)) {
            $deadlines[] = 'resolution';
        }

        $recipientIds = $managers->merge([$report->reporter_id]);
        $recipientIds = $recipientIds->merge($report->assignments->pluck('staff_id'))->filter()->unique();
        foreach ($deadlines as $deadline) {
            foreach ($recipientIds as $userId) {
                $exists = Notification::query()
                    ->where('user_id', $userId)
                    ->where('type', 'report.sla_overdue')
                    ->whereJsonContains('data->report_id', $report->id)
                    ->whereJsonContains('data->deadline', $deadline)
                    ->exists();
                if (! $exists) {
                    Notification::create([
                        'user_id' => $userId,
                        'type' => 'report.sla_overdue',
                        'data' => [
                            'report_id' => $report->id,
                            'ticket_number' => $report->ticket_number,
                            'deadline' => $deadline,
                        ],
                    ]);
                    $created++;
                }
            }
        }
    }

    $this->info("{$created} notifikasi SLA dibuat.");
})->purpose('Buat notifikasi untuk estimasi respons atau penyelesaian yang terlewati');

Schedule::command('reports:notify-overdue')->hourly();
