<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => Notification::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function open(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $report = Report::findOrFail($notification->data['report_id'] ?? 0);
        $this->authorize('view', $report);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return redirect()->route('reports.show', $report);
    }
}
