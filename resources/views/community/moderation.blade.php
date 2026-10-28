<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderasi komunitas</title>
</head>
<body>
    <main>
        <p><a href="{{ route('dashboard') }}">Dashboard</a></p>
        <h1>Moderasi komunitas</h1>
        @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
        @forelse ($reports as $report)
            <article>
                <h2><a href="{{ route('reports.show', $report) }}">{{ $report->title }}</a></h2>
                <p>{{ $report->building->name }} · {{ $report->ticket_number }}</p>
                @if ($report->community_hidden_at)
                    <p>Status komunitas: disembunyikan</p>
                    @if ($report->duplicate_of_report_id)
                        <form method="POST" action="{{ route('community.cancel-duplicate', $report) }}">
                            @csrf
                            <button type="submit">Batalkan status duplikat</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('community.restore', $report) }}">
                            @csrf
                            <button type="submit">Tampilkan kembali</button>
                        </form>
                    @endif
                @else
                    <form method="POST" action="{{ route('community.hide', $report) }}">
                        @csrf
                        <button type="submit">Sembunyikan dari komunitas</button>
                    </form>
                @endif
                @foreach ($report->moderationReports as $moderation)
                    <p>Perlu ditinjau: {{ $moderation->reason }}{{ $moderation->note ? ' — '.$moderation->note : '' }}</p>
                    @if ($moderation->reason === 'duplicate')
                        <form method="POST" action="{{ route('community.review', $report) }}">
                            @csrf
                            <input type="hidden" name="action" value="dismiss_duplicate">
                            <button type="submit">Tolak duplikat</button>
                        </form>
                        <form method="POST" action="{{ route('community.review', $report) }}">
                            @csrf
                            <input type="hidden" name="action" value="confirm_duplicate">
                            <label for="canonical-{{ $report->id }}">Laporan utama</label>
                            <select id="canonical-{{ $report->id }}" name="duplicate_of_report_id" required>
                                <option value="">Pilih laporan utama</option>
                                @foreach (\App\Models\Report::query()->where('visibility', 'public_building')->where('building_id', $report->building_id)->where('id', '!=', $report->id)->latest()->limit(50)->get() as $canonical)
                                    <option value="{{ $canonical->id }}">{{ $canonical->ticket_number }} — {{ $canonical->title }}</option>
                                @endforeach
                            </select>
                            <button type="submit">Konfirmasi duplikat</button>
                        </form>
                    @endif
                @endforeach
            </article>
        @empty
            <p>Tidak ada laporan yang perlu dimoderasi.</p>
        @endforelse
        {{ $reports->links() }}
    </main>
</body>
</html>
