<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ReportNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportWorkflowController extends Controller
{
    public function close(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('close', $report);

        $this->changeStatus($request, $report, 'closed', 'Laporan dikonfirmasi selesai.');
        app(ReportNotificationService::class)->notify(
            $report,
            'report.status_changed',
            'Laporan '.$report->ticket_number.' telah ditutup.',
            $request->user(),
        );

        return redirect()->route('reports.show', $report)
            ->with('success', 'Laporan ditutup.');
    }

    public function reopen(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('reopen', $report);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->changeStatus($request, $report, 'reopened', $data['note']);
        app(ReportNotificationService::class)->notify(
            $report,
            'report.status_changed',
            'Laporan '.$report->ticket_number.' telah dibuka kembali.',
            $request->user(),
        );

        return redirect()->route('reports.show', $report)
            ->with('success', 'Laporan dibuka kembali.');
    }

    private function changeStatus(Request $request, Report $report, string $status, string $note): void
    {
        DB::transaction(function () use ($request, $report, $status, $note): void {
            $oldStatus = $report->status;
            $closedAt = $status === 'closed' ? now() : null;

            $report->update([
                'status' => $status,
                'closed_at' => $closedAt,
                'retained_until' => $closedAt?->copy()->addYears(2),
            ]);

            $report->statusHistories()->create([
                'from_status' => $oldStatus,
                'to_status' => $status,
                'changed_by' => $request->user()->id,
                'note' => $note,
            ]);

            if ($closedAt) {
                $report->attachments()->update(['retained_until' => $closedAt->copy()->addYears(2)]);
                $report->comments()->withoutGlobalScope('not_deleted')->get()->each(
                    fn ($comment) => $comment->attachments()->update([
                        'retained_until' => $closedAt->copy()->addYears(2),
                    ]),
                );
            } else {
                $report->attachments()->update(['retained_until' => null]);
                $report->comments()->withoutGlobalScope('not_deleted')->get()->each(
                    fn ($comment) => $comment->attachments()->update(['retained_until' => null]),
                );
            }
        });
    }
}
