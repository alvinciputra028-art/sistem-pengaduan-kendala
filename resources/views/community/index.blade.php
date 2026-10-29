<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Komunitas</title>
</head>
<body>
    <main>
        <p><a href="{{ route('dashboard') }}">Dashboard</a></p>
        <h1>Komunitas gedung</h1>
        @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
        @forelse ($reports as $report)
            <article>
                <h2><a href="{{ route('reports.show', $report) }}">{{ $report->title }}</a></h2>
                <p>{{ $report->building->name }} · {{ $report->category->name }} · {{ $report->status }}</p>
                <p>{{ $report->description }}</p>
                <p>{{ $report->acknowledgements_count }} penghuni juga mengalami</p>
                <p><a href="{{ route('reports.show', $report) }}">Buka percakapan komunitas</a></p>
                @if (auth()->user()->role === 'resident' && $report->reporter_id !== auth()->id() && !$report->acknowledgements->contains('user_id', auth()->id()))
                    <form method="POST" action="{{ route('community.acknowledge', $report) }}">
                        @csrf
                        <button type="submit">Saya juga mengalami</button>
                    </form>
                @elseif (auth()->user()->role === 'resident' && $report->reporter_id === auth()->id())
                    <p>Anda tidak dapat menandai laporan sendiri.</p>
                @elseif (auth()->user()->role === 'resident')
                    <p>Anda sudah menandai laporan ini.</p>
                @endif
                @if (auth()->user()->role === 'resident' && $report->reporter_id !== auth()->id())
                    <form method="POST" action="{{ route('community.report', $report) }}">
                        @csrf
                        <label for="reason-{{ $report->id }}">Laporkan konten</label>
                        <select id="reason-{{ $report->id }}" name="reason" required>
                            <option value="">Pilih alasan</option>
                            <option value="inappropriate">Konten tidak pantas</option>
                            <option value="personal_data">Data pribadi</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <input name="note" maxlength="2000" placeholder="Catatan (opsional)">
                        <button type="submit">Laporkan</button>
                    </form>
                    <form method="POST" action="{{ route('community.duplicate', $report) }}">
                        @csrf
                        <button type="submit">Tandai duplikat</button>
                    </form>
                @endif
            </article>
        @empty
            <p>Belum ada laporan publik di gedung ini.</p>
        @endforelse
        {{ $reports->links() }}
    </main>
</body>
</html>
