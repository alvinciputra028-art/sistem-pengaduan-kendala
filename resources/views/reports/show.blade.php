<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->ticket_number }}</title>
</head>
<body>
    <main>
        <p><a href="{{ route('reports.index') }}">Kembali ke laporan</a></p>
        @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
        @if ($errors->any())
            <div role="alert" aria-live="assertive">
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <h1>{{ $report->title }}</h1>
        <p><strong>{{ $report->ticket_number }}</strong></p>
        <p>Status: {{ $report->status }} · Prioritas: {{ $report->priority }} ({{ $report->priority_score }})</p>
        @if ($report->estimated_response_at || $report->estimated_resolution_at || $report->estimate_note)
            <h2>Estimasi penanganan</h2>
            @if ($report->estimated_response_at)
                <p>Estimasi respons: {{ $report->estimated_response_at->format('d/m/Y H:i') }}</p>
            @endif
            @if ($report->estimated_resolution_at)
                <p>Estimasi selesai: {{ $report->estimated_resolution_at->format('d/m/Y H:i') }}</p>
            @endif
            @if ($report->estimate_note)
                <p>Catatan estimasi: {{ $report->estimate_note }}</p>
            @endif
        @endif
        @if ($canTriage)
            @include('reports.partials.triage')
        @endif
        @can('close', $report)
            <form method="POST" action="{{ route('reports.close', $report) }}">
                @csrf
                <button type="submit">Konfirmasi selesai dan tutup laporan</button>
            </form>
        @endcan
        @can('reopen', $report)
            <form method="POST" action="{{ route('reports.reopen', $report) }}">
                @csrf
                <label for="reopen-note">Alasan membuka kembali</label>
                <input id="reopen-note" name="note" required maxlength="2000">
                <button type="submit">Buka kembali laporan</button>
            </form>
        @endcan
        <p>Kategori: {{ $report->category->name }}</p>
        <h2>Pelapor</h2>
        @if ($canSeeReporterIdentity)
            <p>Nama: {{ $report->reporter->name }}</p>
        @else
            <p>Nama: Anonim</p>
        @endif
        @if ($canSeeReporterRoom && $report->reporter->room)
            <p>Kamar pelapor: {{ $report->reporter->room->room_number }}</p>
        @endif
        <h2>Petugas</h2>
        @if ($activeAssignment && $activeAssignment->staff)
            <p>Nama: {{ $activeAssignment->staff->name }}</p>
            <p>Unit layanan: {{ $activeAssignment->serviceUnit->name }}</p>
        @else
            <p>Belum ditugaskan.</p>
        @endif
        <p>Lokasi: {{ $report->building->name }}{{ $report->room ? ' - Kamar '.$report->room->room_number : '' }}</p>
        @if ($report->location_description)
            <p>Keterangan lokasi: {{ $report->location_description }}</p>
        @endif
        <div>{{ $report->description }}</div>
        <h2>Bukti</h2>
        @forelse ($report->attachments as $attachment)
            <p><a href="{{ route('attachments.show', $attachment) }}">{{ $attachment->original_name }}</a> ({{ number_format($attachment->size / 1024, 1) }} KB)</p>
        @empty
            <p>Tidak ada bukti.</p>
        @endforelse
        @can('view', $report)
            <h2>{{ $report->visibility === 'public_building' ? 'Percakapan komunitas' : 'Komentar' }}</h2>
            @if ($report->visibility === 'public_building')
                @if ($report->community_chat_disabled_at)
                <p role="status">Percakapan komunitas dinonaktifkan untuk penghuni lain. Komentar lama tetap dapat dibaca.</p>
                @elseif ($report->status === 'closed')
                    <p role="status">Laporan ditutup. Percakapan menjadi hanya-baca.</p>
                @endif
                @if (auth()->user()->role === 'manager')
                    @if ($report->community_chat_disabled_at)
                        <form method="POST" action="{{ route('reports.community-chat.enable', $report) }}">
                            @csrf
                            <button type="submit">Aktifkan percakapan</button>
                        </form>
                    @elseif ($report->status !== 'closed')
                        <form method="POST" action="{{ route('reports.community-chat.disable', $report) }}">
                            @csrf
                            <button type="submit">Sembunyikan percakapan dari penghuni lain</button>
                        </form>
                    @endif
                @endif
            @endif
            @foreach ($report->comments as $comment)
                @if ($comment->deleted_at)
                    @if (auth()->user()->role === 'manager')
                        <p><strong>Komentar disembunyikan:</strong> {{ $comment->body }}</p>
                        <form method="POST" action="{{ route('comments.restore', $comment->id) }}">
                            @csrf
                            <button type="submit">Tampilkan kembali komentar</button>
                        </form>
                    @endif
                @else
                    <p><strong>{{ $comment->user->name }}</strong>: {{ $comment->body }}</p>
                @endif
                @if (auth()->user()->role === 'manager' && $comment->moderationReports->isNotEmpty())
                    <details>
                        <summary>{{ $comment->moderationReports->count() }} laporan moderasi untuk komentar ini</summary>
                        <ul>
                            @foreach ($comment->moderationReports as $moderation)
                                <li>
                                    Alasan: {{ $moderation->reason }} ·
                                    Pelapor: {{ $moderation->reportedBy->name }} ·
                                    Status: {{ $moderation->status }} ·
                                    Dilaporkan: {{ $moderation->created_at->format('d/m/Y H:i') }}
                                    @if ($moderation->note)
                                        · Catatan: {{ $moderation->note }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
                @if (!$comment->deleted_at)
                @foreach ($comment->attachments as $attachment)
                    <p>Lampiran: <a href="{{ route('attachments.show', $attachment) }}">{{ $attachment->original_name }}</a></p>
                @endforeach
                @can('report', $comment)
                    <form method="POST" action="{{ route('comments.report', $comment) }}">
                        @csrf
                        <label for="comment-reason-{{ $comment->id }}">Alasan pelaporan</label>
                        <select id="comment-reason-{{ $comment->id }}" name="reason" required>
                            <option value="">Pilih alasan</option>
                            <option value="inappropriate">Konten tidak pantas</option>
                            <option value="personal_data">Data pribadi</option>
                            <option value="spam">Spam atau promosi</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <input name="note" maxlength="2000" placeholder="Catatan tambahan (opsional)">
                        <button type="submit">Laporkan komentar</button>
                    </form>
                @endcan
                @can('moderate', $comment)
                    <form method="POST" action="{{ route('comments.hide', $comment) }}">
                        @csrf
                        <button type="submit">Sembunyikan komentar</button>
                    </form>
                @endcan
                @endif
            @endforeach
        @endcan
        @can('comment', $report)
            <form method="POST" action="{{ route('reports.comments.store', $report) }}" enctype="multipart/form-data">
                @csrf
                <label for="comment-body">Komentar</label>
                <textarea id="comment-body" name="body" required maxlength="5000"></textarea>
                <label for="comment-attachments">Lampiran</label>
                <input id="comment-attachments" type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple>
                <p id="comment-attachments-error" role="alert" hidden></p>
                <button type="submit">Kirim komentar</button>
            </form>
        @endcan
        <h2>Riwayat status</h2>
        <ul>
            @foreach ($report->statusHistories as $history)
                <li>{{ $history->to_status }} — {{ $history->created_at->format('d/m/Y H:i') }}{{ $history->note ? ': '.$history->note : '' }}</li>
            @endforeach
        </ul>
    </main>
    <script>
        const commentForm = document.querySelector('form[action="{{ route('reports.comments.store', $report) }}"]');
        const commentAttachments = document.getElementById('comment-attachments');
        const commentAttachmentsError = document.getElementById('comment-attachments-error');
        const maxCommentAttachmentSize = 10 * 1024 * 1024;
        const allowedCommentTypes = ['image/jpeg', 'image/png', 'image/webp'];

        function validateCommentAttachments() {
            const files = [...commentAttachments.files];
            let message = '';
            if (files.length > 5) {
                message = 'Maksimal 5 lampiran komentar dapat dipilih.';
            } else if (files.some((file) => !allowedCommentTypes.includes(file.type))) {
                message = 'Format lampiran harus JPG, JPEG, PNG, atau WEBP.';
            } else if (files.some((file) => file.size > maxCommentAttachmentSize)) {
                message = 'Ukuran setiap lampiran maksimal 10 MB.';
            }
            commentAttachmentsError.textContent = message;
            commentAttachmentsError.hidden = !message;
            commentAttachments.setCustomValidity(message);
            return !message;
        }

        if (commentForm && commentAttachments) {
            commentAttachments.addEventListener('change', validateCommentAttachments);
            commentForm.addEventListener('submit', (event) => {
                if (!validateCommentAttachments()) {
                    event.preventDefault();
                }
            });
        }
    </script>
</body>
</html>
