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
        <h1>{{ $report->title }}</h1>
        <p><strong>{{ $report->ticket_number }}</strong></p>
        <p>Status: {{ $report->status }} · Prioritas: {{ $report->priority }} ({{ $report->priority_score }})</p>
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
        @can('comment', $report)
            <h2>Komentar</h2>
            @foreach ($report->comments as $comment)
                <p><strong>{{ $comment->user->name }}</strong>: {{ $comment->body }}</p>
            @endforeach
            <form method="POST" action="{{ route('reports.comments.store', $report) }}">
                @csrf
                <label for="comment-body">Komentar</label>
                <textarea id="comment-body" name="body" required maxlength="5000"></textarea>
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
</body>
</html>
