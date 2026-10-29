<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
class ReportNotificationService
{
    public function notify(Report $report, string $type, string $message, ?User $actor = null): void
    {
        $report->loadMissing(['assignments' => fn ($query) => $query->whereNull('unassigned_at')]);

        $recipientIds = collect([$report->reporter_id])
            ->merge($report->assignments->pluck('staff_id'))
            ->merge($report->acknowledgements()->pluck('user_id'))
            ->merge(User::query()->where('role', 'manager')->where('is_active', true)->pluck('id'))
            ->filter()
            ->unique();

        if ($actor) {
            $recipientIds = $recipientIds->reject(fn (int $id): bool => $id === $actor->id);
        }

        foreach ($recipientIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'data' => [
                    'report_id' => $report->id,
                    'ticket_number' => $report->ticket_number,
                    'message' => $message,
                ],
            ]);
        }
    }
}
