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
                    @if (auth()->user()->role === 'staff' && $report->reporter_id !== auth()->id())
                        <form method="POST" action="{{ route('community.report', $report) }}">
                            @csrf
                            <label for="report-reason-{{ $report->id }}">Laporkan untuk moderasi</label>
                            <select id="report-reason-{{ $report->id }}" name="reason" required>
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
                    @if (auth()->user()->role === 'manager')
                        @foreach ($report->moderationReports as $moderation)
                            <details>
                                <summary>Flag komunitas: {{ $moderation->reason }}</summary>
                                <p>Pelapor laporan: {{ $report->reporter->name }}</p>
                                <p>Pengaju flag: {{ $moderation->reportedBy->name }}</p>
                                <p>Dibuat: {{ $moderation->created_at->format('d/m/Y H:i') }}</p>
                                @if ($moderation->note)<p>Catatan: {{ $moderation->note }}</p>@endif
                                @if ($moderation->reason === 'duplicate')
                                    <form method="POST" action="{{ route('community.review', $report) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="dismiss_duplicate">
                                        <button type="submit">Tolak duplikat</button>
                                    </form>
                                    <form method="POST" action="{{ route('community.review', $report) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="confirm_duplicate">
                                        <label for="canonical-{{ $report->id }}-{{ $moderation->id }}">Laporan utama</label>
                                        <select id="canonical-{{ $report->id }}-{{ $moderation->id }}" name="duplicate_of_report_id" required>
                                            <option value="">Pilih laporan utama</option>
                                            @foreach ($duplicateCandidates->where('building_id', $report->building_id)->where('id', '!=', $report->id) as $canonical)
                                                <option value="{{ $canonical->id }}">{{ $canonical->ticket_number }} — {{ $canonical->title }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit">Konfirmasi duplikat</button>
                                    </form>
                                @endif
                            </details>
                        @endforeach
                        @if ($report->community_hidden_at)
                            <form method="POST" action="{{ $report->duplicate_of_report_id ? route('community.cancel-duplicate', $report) : route('community.restore', $report) }}">
                                @csrf
                                <button type="submit">{{ $report->duplicate_of_report_id ? 'Batalkan status duplikat' : 'Tampilkan kembali di komunitas' }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('community.hide', $report) }}">
                                @csrf
                                <button type="submit">Sembunyikan dari komunitas</button>
                            </form>
                        @endif
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
