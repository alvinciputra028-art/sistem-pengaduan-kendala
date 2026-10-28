<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ in_array(auth()->user()->role, ['staff', 'manager'], true) ? 'Antrean dan daftar laporan' : 'Daftar laporan' }}</title>
</head>
<body>
    <main>
        <p><a href="{{ route('dashboard') }}">Dashboard</a></p>
        <h1>{{ in_array(auth()->user()->role, ['staff', 'manager'], true) ? 'Antrean dan daftar laporan' : 'Daftar laporan' }}</h1>
        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if (auth()->user()->role === 'resident')
            <p><a href="{{ route('reports.create') }}">Buat laporan</a></p>
        @endif
        @if (in_array(auth()->user()->role, ['staff', 'manager'], true))
            <form method="GET" action="{{ route('reports.index') }}">
                <label for="building_id">Gedung</label>
                <select id="building_id" name="building_id">
                    <option value="">Semua gedung</option>
                    @foreach ($buildings as $building)
                        <option value="{{ $building->id }}" @selected(($filters['building_id'] ?? null) == $building->id)>{{ $building->name }}</option>
                    @endforeach
                </select>
                <label for="service_unit_id">Unit layanan</label>
                <select id="service_unit_id" name="service_unit_id">
                    <option value="">Semua unit</option>
                    @foreach ($serviceUnits as $unit)
                        <option value="{{ $unit->id }}" @selected(($filters['service_unit_id'] ?? null) == $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    @foreach (['submitted', 'triaged', 'assigned', 'in_progress', 'waiting_confirmation', 'reopened', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <label for="priority">Prioritas</label>
                <select id="priority" name="priority">
                    <option value="">Semua prioritas</option>
                    @foreach (['critical', 'high', 'normal', 'low'] as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
                <label for="age">Usia</label>
                <select id="age" name="age">
                    <option value="">Semua usia</option>
                    <option value="under_1_day" @selected(($filters['age'] ?? null) === 'under_1_day')>&lt; 1 hari</option>
                    <option value="1_to_3_days" @selected(($filters['age'] ?? null) === '1_to_3_days')>1-3 hari</option>
                    <option value="over_3_days" @selected(($filters['age'] ?? null) === 'over_3_days')>&gt; 3 hari</option>
                </select>
                <button type="submit">Terapkan filter</button>
            </form>
        @endif
        @forelse ($reports as $report)
            <article>
                <h2><a href="{{ route('reports.show', $report) }}">{{ $report->title }}</a></h2>
                <p>{{ $report->ticket_number }} · {{ $report->category->name }} · {{ $report->status }}</p>
                <p>Prioritas: {{ $report->priority }} ({{ $report->priority_score }})</p>
                <p>{{ $report->building->name }}</p>
                @if (auth()->user()->role !== 'resident')
                    <p>Dibuat {{ $report->created_at->diffForHumans() }}</p>
                    @if ($report->assignments->first())
                        <p>{{ $report->assignments->first()->serviceUnit->name }} — {{ $report->assignments->first()->staff?->name ?? 'Belum ada petugas' }}</p>
                    @endif
                @endif
            </article>
        @empty
            <p>Belum ada laporan yang dapat ditampilkan.</p>
        @endforelse
        {{ $reports->links() }}
    </main>
</body>
</html>
