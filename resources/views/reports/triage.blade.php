<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Triase {{ $report->ticket_number }}</title>
</head>
<body>
    <main>
        <p><a href="{{ route('reports.show', $report) }}">Lihat laporan</a></p>
        <h1>Triase laporan {{ $report->ticket_number }}</h1>
        @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
        @if ($errors->any())
            <div role="alert">
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <p><strong>{{ $report->title }}</strong></p>
        <p>Status saat ini: {{ $report->status }} · Prioritas saat ini: {{ $report->priority }}</p>

        <form method="POST" action="{{ route('reports.triage.update', $report) }}">
            @csrf
            @method('PATCH')
            <label for="priority">Prioritas final</label>
            <select id="priority" name="priority" required>
                @foreach (['low', 'normal', 'high', 'critical'] as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $report->priority) === $priority)>{{ $priority }}</option>
                @endforeach
            </select>
            <br>
            <p>Status setelah simpan triase:
                @if (auth()->user()->role === 'manager' && in_array($report->status, ['submitted', 'reopened'], true))
                    <strong>triaged</strong>
                @else
                    <strong>{{ $report->status }}</strong>
                @endif
            </p>
            <label for="reason">Alasan perubahan prioritas</label>
            <textarea id="reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea>
            <br>
            <label for="estimated_response_at">Estimasi respons</label>
            <input id="estimated_response_at" type="datetime-local" name="estimated_response_at" value="{{ old('estimated_response_at', optional($report->estimated_response_at)->format('Y-m-d\TH:i')) }}">
            <br>
            <label for="estimated_resolution_at">Estimasi selesai</label>
            <input id="estimated_resolution_at" type="datetime-local" name="estimated_resolution_at" value="{{ old('estimated_resolution_at', optional($report->estimated_resolution_at)->format('Y-m-d\TH:i')) }}">
            <br>
            <label for="estimate_note">Catatan estimasi</label>
            <textarea id="estimate_note" name="estimate_note" maxlength="2000">{{ old('estimate_note', $report->estimate_note) }}</textarea>
            <br>
            <button type="submit">Simpan triase</button>
        </form>

        @if (auth()->user()->role === 'staff')
            <h2>Aksi penanganan</h2>
            @foreach ([
                'in_progress' => 'Mulai tangani',
                'resolved' => 'Tandai selesai',
            ] as $status => $label)
                @if (in_array($status, $availableTransitions, true))
                    <form method="POST" action="{{ route('reports.triage.status', $report) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $status }}">
                        <label for="note-{{ $status }}">Catatan</label>
                        <input id="note-{{ $status }}" name="note" required maxlength="2000">
                        <button type="submit">{{ $label }}</button>
                    </form>
                @endif
            @endforeach
        @endif

        <h2>Assignment aktif</h2>
        @if ($activeAssignment)
            <p>{{ $activeAssignment->serviceUnit->name }} — {{ $activeAssignment->staff->name }}</p>
            <p>{{ $activeAssignment->note }}</p>
        @else
            <p>Belum ada assignment aktif.</p>
        @endif

        @if (auth()->user()->role === 'manager')
            <h2>Tugaskan laporan</h2>
            <form method="POST" action="{{ route('reports.assignments.store', $report) }}">
                @csrf
                <label for="service_unit_id">Unit layanan</label>
                <select id="service_unit_id" name="service_unit_id" required>
                    <option value="">Pilih unit</option>
                    @foreach ($serviceUnits as $unit)
                        <option value="{{ $unit->id }}" @selected($recommendedUnit?->id === $unit->id)>
                            {{ $unit->name }}{{ $recommendedUnit?->id === $unit->id ? ' (rekomendasi kategori)' : '' }}
                        </option>
                    @endforeach
                </select>
                @if ($recommendedUnit)
                    <p>Rekomendasi sistem: {{ $recommendedUnit->name }} berdasarkan unit default kategori.</p>
                @else
                    <p role="alert">Unit default kategori belum tersedia untuk gedung ini. Pilih unit secara manual.</p>
                @endif
                <br>
                <label for="staff_id">Petugas</label>
                <select id="staff_id" name="staff_id" required>
                    <option value="">Pilih petugas</option>
                    @foreach ($serviceUnits as $unit)
                        @foreach ($unit->staff as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }} ({{ $unit->name }})</option>
                        @endforeach
                    @endforeach
                </select>
                <br>
                <label for="assignment_note">Catatan assignment</label>
                <textarea id="assignment_note" name="note" maxlength="2000"></textarea>
                <br>
                <button type="submit">Tugaskan</button>
            </form>
        @endif
    </main>
</body>
</html>
