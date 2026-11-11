@extends('layouts.app')

@section('title', $report->ticket_number)

@section('content')
@php
    $statusLabels = [
        'submitted' => 'Dikirim',
        'triaged' => 'Ditriase',
        'assigned' => 'Ditugaskan',
        'in_progress' => 'Sedang dikerjakan',
        'waiting_confirmation' => 'Menunggu konfirmasi',
        'reopened' => 'Dibuka kembali',
        'closed' => 'Ditutup',
    ];
    $priorityLabels = [
        'low' => 'Rendah',
        'normal' => 'Normal',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
    ];
@endphp

<div class="page-heading">
    <div>
        <p class="eyebrow">Detail laporan</p>
        <h1>{{ $report->title }}</h1>
        <p class="report-ticket">{{ $report->ticket_number }} · Dibuat {{ $report->created_at->format('d/m/Y H:i') }}</p>
    </div>
    <a class="button button--secondary button--small" href="{{ route('reports.index') }}">Kembali ke laporan</a>
</div>

<div class="report-detail-grid">
    <div class="report-detail-main">
        <section class="card report-summary" aria-labelledby="summary-heading">
            <h2 id="summary-heading">Ringkasan laporan</h2>
            <div class="report-summary__badges">
                <span class="status-badge status-badge--{{ $report->status }}">{{ $statusLabels[$report->status] ?? $report->status }}</span>
                <span class="priority-badge priority-badge--{{ $report->priority }}">{{ $priorityLabels[$report->priority] ?? $report->priority }} ({{ $report->priority_score }})</span>
            </div>
            <dl class="detail-list">
                <div><dt>Kategori</dt><dd>{{ $report->category->name }}</dd></div>
                <div><dt>Lokasi</dt><dd>{{ $report->building->name }}{{ $report->room ? ' · Kamar '.$report->room->room_number : '' }}</dd></div>
                @if ($report->location_description)
                    <div><dt>Keterangan lokasi</dt><dd>{{ $report->location_description }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="card" aria-labelledby="description-heading">
            <h2 id="description-heading">Uraian kendala</h2>
            <p class="report-description">{{ $report->description }}</p>
        </section>

        <section class="card" aria-labelledby="attachment-heading">
            <h2 id="attachment-heading">Bukti pendukung</h2>
            @forelse ($report->attachments as $attachment)
                <ul class="attachment-list">
                    <li class="attachment-item">
                        <a href="{{ route('attachments.show', $attachment) }}">{{ $attachment->original_name }}</a>
                        <small>{{ number_format($attachment->size / 1024, 1) }} KB · {{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</small>
                    </li>
                </ul>
            @empty
                <p class="empty-state">Tidak ada bukti yang dilampirkan.</p>
            @endforelse
        </section>

        <section class="card report-actions" aria-labelledby="actions-heading">
            <div class="section-heading">
                <h2 id="actions-heading">Aksi laporan</h2>
                @if ($canTriage)
                    <a class="button button--secondary button--small" href="{{ route('reports.triage.edit', $report) }}">Buka triase &amp; assignment</a>
                @endif
            </div>
            @if ($canTriage)
                <p class="form-helper">Kelola prioritas, estimasi, assignment, dan penanganan dari halaman kerja operasional.</p>
            @endif
            @if ($report->status === 'closed')
                <p role="status">Laporan ini ditutup dan bersifat read-only. Buka kembali laporan jika masalah belum selesai.</p>
            @endif
            @can('close', $report)
                <form method="POST" action="{{ route('reports.close', $report) }}" data-confirm="Tutup laporan ini? Pastikan pekerjaan sudah selesai.">
                    @csrf
                    <button class="button button--success" type="submit">Konfirmasi selesai dan tutup laporan</button>
                </form>
            @endcan
            @can('reopen', $report)
                <form method="POST" action="{{ route('reports.reopen', $report) }}">
                    @csrf
                    <label for="reopen-note">Alasan membuka kembali</label>
                    <input id="reopen-note" name="note" required maxlength="2000">
                    <button class="button button--warning" type="submit">Buka kembali laporan</button>
                </form>
            @endcan
        </section>

        @can('view', $report)
            <section class="card" aria-labelledby="conversation-heading">
                <div class="section-heading">
                    <div>
                        <h2 id="conversation-heading">{{ $report->visibility === 'public_building' ? 'Percakapan komunitas' : 'Komentar internal' }}</h2>
                        <p class="form-helper">
                            <span class="visibility-label">{{ $report->visibility === 'public_building' ? 'Publik untuk penghuni gedung' : 'Hanya pihak yang memiliki akses laporan' }}</span>
                        </p>
                    </div>
                    <span class="conversation-status {{ $report->status === 'closed' ? 'conversation-status--closed' : ($report->community_chat_disabled_at ? 'conversation-status--disabled' : 'conversation-status--active') }}">
                        {{ $report->status === 'closed' ? 'Hanya baca' : ($report->community_chat_disabled_at ? 'Dibatasi' : 'Aktif') }}
                    </span>
                </div>
                @if ($report->visibility === 'public_building' && $report->community_chat_disabled_at)
                    <p class="alert alert--warning" role="status">Percakapan dinonaktifkan untuk penghuni lain. Komentar lama tetap dapat dibaca.</p>
                @elseif ($report->status === 'closed')
                    <p class="alert alert--info" role="status">Laporan ditutup. Percakapan menjadi hanya-baca.</p>
                @endif
                @if (auth()->user()->role === 'manager' && $report->visibility === 'public_building')
                    @if ($report->community_chat_disabled_at)
                        <form class="conversation-action" method="POST" action="{{ route('reports.community-chat.enable', $report) }}" data-confirm="Aktifkan kembali percakapan komunitas?">
                            @csrf
                            <button class="button button--success" type="submit">Aktifkan percakapan</button>
                        </form>
                    @elseif ($report->status !== 'closed')
                        <form class="conversation-action" method="POST" action="{{ route('reports.community-chat.disable', $report) }}" data-confirm="Sembunyikan percakapan dari penghuni lain?">
                            @csrf
                            <button class="button button--warning" type="submit">Sembunyikan percakapan</button>
                        </form>
                    @endif
                @endif
                @foreach ($report->comments as $comment)
                    <article class="comment-item">
                        @if ($comment->deleted_at)
                            @if (auth()->user()->role === 'manager')
                                <p><strong>Komentar disembunyikan:</strong> {{ $comment->body }}</p>
                                <form method="POST" action="{{ route('comments.restore', $comment->id) }}" data-confirm="Tampilkan kembali komentar ini?">
                                    @csrf
                                    <button class="button button--success button--small" type="submit">Tampilkan kembali</button>
                                </form>
                            @endif
                        @else
                            <p>
                                <strong>{{ $comment->user_id === $report->reporter_id && !$canSeeReporterIdentity ? 'Penghuni gedung' : $comment->user->name }}</strong>
                                <small>{{ $comment->created_at->format('d/m/Y H:i') }}</small>
                                <span class="comment-visibility">{{ $comment->visibility === 'internal' ? 'Internal' : ($report->visibility === 'public_building' ? 'Komunitas' : 'Peserta') }}</span>
                            </p>
                            <p>{{ $comment->body }}</p>
                            @if (auth()->user()->role === 'manager' && $comment->moderationReports->isNotEmpty())
                                <details>
                                    <summary>{{ $comment->moderationReports->count() }} laporan moderasi untuk komentar ini</summary>
                                    <ul>
                                        @foreach ($comment->moderationReports as $moderation)
                                            <li>
                                                Alasan: {{ $moderation->reason }} · Pelapor: {{ $moderation->reportedBy->name }} ·
                                                Status: {{ $moderation->status }} · {{ $moderation->created_at->format('d/m/Y H:i') }}
                                                @if ($moderation->note) · {{ $moderation->note }}@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                            @foreach ($comment->attachments as $attachment)
                                <p><small>Lampiran:</small> <a href="{{ route('attachments.show', $attachment) }}">{{ $attachment->original_name }}</a></p>
                            @endforeach
                            @can('report', $comment)
                                <button class="button button--warning button--small report-toggle" type="button" aria-expanded="false" aria-controls="comment-report-{{ $comment->id }}">Laporkan komentar</button>
                                <form id="comment-report-{{ $comment->id }}" class="report-reason-form" method="POST" action="{{ route('comments.report', $comment) }}" hidden>
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
                                    <button class="button button--warning button--small" type="submit">Kirim laporan komentar</button>
                                </form>
                            @endcan
                            @can('moderate', $comment)
                                <form method="POST" action="{{ route('comments.hide', $comment) }}" data-confirm="Sembunyikan komentar ini?">
                                    @csrf
                                    <button class="button button--danger button--small" type="submit">Sembunyikan komentar</button>
                                </form>
                            @endcan
                        @endif
                    </article>
                @endforeach
            @endcan
            @can('comment', $report)
                <form method="POST" action="{{ route('reports.comments.store', $report) }}" enctype="multipart/form-data">
                    @csrf
                    <p class="form-helper">Komentar dibatasi 10 pengiriman per menit. Gunakan teks biasa dan jangan membagikan nomor kamar atau kontak pribadi.</p>
                    <label for="comment-body">Komentar</label>
                    <textarea id="comment-body" name="body" required maxlength="5000"></textarea>
                    <label for="comment-attachments">Lampiran komentar <small>(maksimal 5 gambar, 10 MB per file)</small></label>
                    <input id="comment-attachments" type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <p id="comment-attachments-error" role="alert" hidden></p>
                    <ul id="comment-attachment-list"></ul>
                    <button class="button" type="submit" data-loading-label="Mengirim komentar...">Kirim komentar</button>
                </form>
            @elseif ($report->visibility === 'public_building' && auth()->user()->role === 'resident' && $report->reporter_id !== auth()->id() && $report->status !== 'closed' && !$report->community_chat_disabled_at)
                <p class="alert alert--info" role="status">Pilih <strong>“Saya juga mengalami”</strong> pada halaman komunitas sebelum dapat ikut berkomentar.</p>
            @elseif ($report->status === 'closed')
                <p role="status">Komentar tidak tersedia karena laporan sudah ditutup.</p>
            @endcan
        </section>
    </div>

    <aside class="report-detail-side">
        <section class="card" aria-labelledby="people-heading">
            <h2 id="people-heading">Pihak terkait</h2>
            <dl class="detail-list">
                <div><dt>Pelapor</dt><dd>Nama: {{ $canSeeReporterIdentity ? $report->reporter->name : 'Anonim' }}</dd></div>
                @if ($canSeeReporterRoom && $report->reporter->room)
                    <div><dt>Kamar pelapor</dt><dd>Kamar pelapor: {{ $report->reporter->room->room_number }}</dd></div>
                @endif
                <div><dt>Petugas</dt><dd>{{ $activeAssignment?->staff ? 'Nama: '.$activeAssignment->staff->name : 'Belum ditugaskan.' }}</dd></div>
                @if ($activeAssignment?->serviceUnit)
                    <div><dt>Unit layanan</dt><dd>Unit layanan: {{ $activeAssignment->serviceUnit->name }}</dd></div>
                @endif
            </dl>
        </section>
        <section class="card" aria-labelledby="estimate-heading">
            <h2 id="estimate-heading">Estimasi penanganan</h2>
            @if ($report->estimated_response_at || $report->estimated_resolution_at || $report->estimate_note)
                <dl class="detail-list">
                    @if ($report->estimated_response_at)<div><dt>Estimasi respons</dt><dd>Estimasi respons: {{ $report->estimated_response_at->format('d/m/Y H:i') }}</dd></div>@endif
                    @if ($report->estimated_resolution_at)<div><dt>Estimasi selesai</dt><dd>Estimasi selesai: {{ $report->estimated_resolution_at->format('d/m/Y H:i') }}</dd></div>@endif
                    @if ($report->estimate_note)<div><dt>Catatan</dt><dd>Catatan estimasi: {{ $report->estimate_note }}</dd></div>@endif
                </dl>
            @else
                <p class="empty-state">Belum ada estimasi.</p>
            @endif
        </section>
        <section class="card" aria-labelledby="timeline-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Jejak perubahan</p>
                    <h2 id="timeline-heading">Timeline status</h2>
                </div>
            </div>
            @if ($report->statusHistories->isNotEmpty())
                <ol class="timeline">
                    @foreach ($report->statusHistories->sortBy('created_at') as $history)
                        <li class="timeline__item">
                            <span class="timeline__marker" aria-hidden="true"></span>
                            <div>
                                <strong>{{ $statusLabels[$history->to_status] ?? $history->to_status }}</strong>
                                <p>{{ $history->changedBy?->name ?? 'Sistem' }} · {{ $history->created_at->format('d/m/Y H:i') }}</p>
                                @if ($history->note)<p class="timeline__note">{{ $history->note }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="empty-state">Belum ada perubahan status.</p>
            @endif
        </section>
        <section class="card" aria-labelledby="priority-history-heading">
            <h2 id="priority-history-heading">Riwayat prioritas</h2>
            @if ($report->priorityHistories->isNotEmpty())
                <ul class="history-list">
                    @foreach ($report->priorityHistories->sortByDesc('created_at') as $history)
                        <li><strong>{{ $priorityLabels[$history->new_priority] ?? $history->new_priority }}</strong> ({{ $history->score }}) · {{ $history->changedBy?->name ?? 'Sistem' }} · {{ $history->created_at->format('d/m/Y H:i') }}@if ($history->reason) — {{ $history->reason }}@endif</li>
                    @endforeach
                </ul>
            @else
                <p class="empty-state">Belum ada perubahan prioritas.</p>
            @endif
        </section>
    </aside>
</div>

<script>
    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
    const commentForm = document.querySelector('form[action="{{ route('reports.comments.store', $report) }}"]');
    const commentAttachments = document.getElementById('comment-attachments');
    const commentAttachmentsError = document.getElementById('comment-attachments-error');
    const commentAttachmentList = document.getElementById('comment-attachment-list');
    const allowedCommentTypes = ['image/jpeg', 'image/png', 'image/webp'];
    function validateCommentAttachments() {
        const files = [...commentAttachments.files];
        let message = '';
        if (files.length > 5) message = 'Maksimal 5 lampiran komentar dapat dipilih.';
        else if (files.some((file) => !allowedCommentTypes.includes(file.type))) message = 'Format lampiran harus JPG, JPEG, PNG, atau WEBP.';
        else if (files.some((file) => file.size > 10 * 1024 * 1024)) message = 'Ukuran setiap lampiran maksimal 10 MB.';
        commentAttachmentsError.textContent = message;
        commentAttachmentsError.hidden = !message;
        commentAttachments.setCustomValidity(message);
        return !message;
    }
    if (commentForm && commentAttachments) {
        commentAttachments.addEventListener('change', () => {
            commentAttachmentList.replaceChildren(...[...commentAttachments.files].map((file, index) => {
                const item = document.createElement('li');
                item.append(document.createTextNode(file.name + ' '));
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button button--danger button--icon';
                remove.textContent = '−';
                remove.setAttribute('aria-label', `Hapus ${file.name}`);
                remove.addEventListener('click', () => {
                    const dataTransfer = new DataTransfer();
                    [...commentAttachments.files].forEach((current, currentIndex) => {
                        if (currentIndex !== index) dataTransfer.items.add(current);
                    });
                    commentAttachments.files = dataTransfer.files;
                    commentAttachments.dispatchEvent(new Event('change'));
                });
                item.append(remove);
                return item;
            }));
            validateCommentAttachments();
        });
        commentForm.addEventListener('submit', (event) => {
            if (!validateCommentAttachments()) event.preventDefault();
        });
    }
</script>
@endsection
