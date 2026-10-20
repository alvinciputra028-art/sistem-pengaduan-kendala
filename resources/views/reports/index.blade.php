<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Laporan</title>
</head>
<body>
    <main>
        <p><a href="{{ route('dashboard') }}">Dashboard</a></p>
        <h1>Daftar Laporan</h1>
        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if (auth()->user()->role === 'resident')
            <p><a href="{{ route('reports.create') }}">Buat laporan</a></p>
        @endif
        @forelse ($reports as $report)
            <article>
                <h2><a href="{{ route('reports.show', $report) }}">{{ $report->title }}</a></h2>
                <p>{{ $report->ticket_number }} · {{ $report->category->name }} · {{ $report->status }}</p>
                <p>Prioritas: {{ $report->priority }} ({{ $report->priority_score }})</p>
                <p>{{ $report->building->name }}</p>
                @if (auth()->user()->role !== 'resident')
                    <p><a href="{{ route('reports.triage.edit', $report) }}">Buka triase</a></p>
                @endif
            </article>
        @empty
            <p>Belum ada laporan yang dapat ditampilkan.</p>
        @endforelse
        {{ $reports->links() }}
    </main>
</body>
</html>
