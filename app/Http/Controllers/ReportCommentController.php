<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportCommentModerationReport;
use App\Models\Notification;
use App\Models\User;
use App\Services\ReportNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportCommentController extends Controller
{
    public function store(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('comment', $report);
        abort_unless(
            $report->visibility !== 'public_building'
                || $report->community_chat_disabled_at === null
                || $request->user()->role !== 'resident'
                || $report->reporter_id === $request->user()->id,
            422,
            'Percakapan komunitas sedang dinonaktifkan.',
        );

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'attachments.max' => 'Maksimal 5 lampiran komentar dapat dipilih.',
            'attachments.*.image' => 'Setiap lampiran komentar harus berupa gambar.',
            'attachments.*.mimes' => 'Format lampiran harus JPG, JPEG, PNG, atau WEBP.',
            'attachments.*.max' => 'Ukuran setiap lampiran maksimal 10 MB.',
        ]);

        DB::transaction(function () use ($data, $report, $request): void {
            $comment = new ReportComment([
                'body' => $data['body'],
                'user_id' => $request->user()->id,
            ]);
            $report->comments()->save($comment);

            foreach ($data['attachments'] ?? [] as $file) {
                $path = $file->store('comments', 'local');
                if ($path === false) {
                    throw new \RuntimeException('Lampiran komentar gagal disimpan.');
                }

                $comment->attachments()->create([
                    'uploaded_by' => $request->user()->id,
                    'storage_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath()),
                ]);
            }
        });

        app(ReportNotificationService::class)->notify(
            $report,
            'report.comment_added',
            'Komentar baru ditambahkan pada laporan '.$report->ticket_number.'.',
            $request->user(),
        );

        return redirect()->route('reports.show', $report)
            ->with('success', 'Komentar berhasil ditambahkan.');
    }

    public function report(Request $request, ReportComment $comment): RedirectResponse
    {
        $this->authorize('report', $comment);
        $data = $request->validate([
            'reason' => ['required', 'in:inappropriate,personal_data,spam,other'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        ReportCommentModerationReport::updateOrCreate(
            ['comment_id' => $comment->id, 'reported_by' => $request->user()->id],
            [
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'status' => 'open',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );

        $report = $comment->report;
        $reporterName = $comment->user?->name ?? 'pengguna';
        $excerpt = str($comment->body)->limit(120);
        $message = 'Komentar #'.$comment->id.' oleh '.$reporterName
            .' pada laporan '.$report->ticket_number
            .' dilaporkan ('.$data['reason'].'): '.$excerpt;
        foreach (User::query()->where('role', 'manager')->where('is_active', true)->get() as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => 'report.comment_reported',
                'data' => [
                    'report_id' => $report->id,
                    'comment_id' => $comment->id,
                    'ticket_number' => $report->ticket_number,
                    'message' => $message,
                ],
            ]);
        }

        return back()->with('success', 'Komentar dikirim untuk ditinjau.');
    }

    public function hide(Request $request, ReportComment $comment): RedirectResponse
    {
        $this->authorize('moderate', $comment);

        $comment->update([
            'deleted_by' => $request->user()->id,
            'deleted_at' => now(),
        ]);
        $comment->moderationReports()->where('status', 'open')->update([
            'status' => 'resolved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Komentar disembunyikan dari percakapan.');
    }

    public function restore(Request $request, int $comment): RedirectResponse
    {
        $commentModel = ReportComment::withoutGlobalScope('not_deleted')->findOrFail($comment);
        $this->authorize('moderate', $commentModel);

        $commentModel->update([
            'deleted_by' => null,
            'deleted_at' => null,
        ]);
        $commentModel->moderationReports()->where('status', 'resolved')->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Komentar ditampilkan kembali dalam percakapan.');
    }

    public function disableConversation(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->role === 'manager', 403);
        abort_unless($report->visibility === 'public_building', 422, 'Percakapan hanya tersedia pada laporan publik.');
        abort_unless(
            in_array($report->status, [
                'submitted',
                'triaged',
                'assigned',
                'in_progress',
                'waiting_confirmation',
                'reopened',
            ], true),
            422,
            'Percakapan hanya dapat dinonaktifkan selama laporan masih aktif.',
        );

        $report->update([
            'community_chat_disabled_at' => now(),
            'community_chat_disabled_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Percakapan komunitas dinonaktifkan.');
    }

    public function enableConversation(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->role === 'manager', 403);
        abort_unless($report->visibility === 'public_building', 422, 'Percakapan hanya tersedia pada laporan publik.');

        $report->update([
            'community_chat_disabled_at' => null,
            'community_chat_disabled_by' => null,
        ]);

        return back()->with('success', 'Percakapan komunitas diaktifkan kembali.');
    }
}
