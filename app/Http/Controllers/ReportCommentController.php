<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportComment;
use App\Services\ReportNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportCommentController extends Controller
{
    public function store(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('comment', $report);

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
}
