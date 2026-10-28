<section aria-labelledby="triage-heading">
    <h2 id="triage-heading">Triase dan penanganan</h2>
    <p>Status saat ini: {{ $report->status }} · Prioritas saat ini: {{ $report->priority }}</p>

    @can('updatePriority', $report)
        <form method="POST" action="{{ route('reports.triage.update', $report) }}">
            @csrf
            @method('PATCH')
            <label for="priority">Prioritas final</label>
            <select id="priority" name="priority" required>
                @foreach (['low', 'normal', 'high', 'critical'] as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $report->priority) === $priority)>{{ $priority }}</option>
                @endforeach
            </select>
            <label for="reason">Alasan perubahan prioritas</label>
            <textarea id="reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea>
            <label for="estimated_response_at">Estimasi respons (WIB)</label>
            <input id="estimated_response_at" type="datetime-local" name="estimated_response_at" value="{{ old('estimated_response_at', optional($report->estimated_response_at)->format('Y-m-d\TH:i')) }}">
            <label for="estimated_resolution_at">Estimasi selesai (WIB)</label>
            <input id="estimated_resolution_at" type="datetime-local" name="estimated_resolution_at" value="{{ old('estimated_resolution_at', optional($report->estimated_resolution_at)->format('Y-m-d\TH:i')) }}">
            <label for="estimate_note">Catatan estimasi</label>
            <textarea id="estimate_note" name="estimate_note" maxlength="2000">{{ old('estimate_note', $report->estimate_note) }}</textarea>
            <label for="estimate_reason">Alasan perubahan estimasi</label>
            <textarea id="estimate_reason" name="estimate_reason" maxlength="2000">{{ old('estimate_reason') }}</textarea>
            <button type="submit">Simpan triase</button>
        </form>
    @else
        <p role="status">Prioritas dan estimasi tidak dapat diubah setelah pekerjaan menunggu konfirmasi penghuni atau laporan ditutup.</p>
    @endcan

    @if (auth()->user()->role === 'staff')
        <h3>Aksi penanganan</h3>
        @foreach ([
            'in_progress' => 'Mulai tangani',
            'waiting_confirmation' => 'Tandai menunggu konfirmasi penghuni',
        ] as $status => $label)
            @if (in_array($status, $availableTransitions, true))
                <form method="POST" action="{{ route('reports.triage.status', $report) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $status }}">
                    <label for="show-note-{{ $status }}">Catatan</label>
                    <input id="show-note-{{ $status }}" name="note" required maxlength="2000">
                    <button type="submit">{{ $label }}</button>
                </form>
            @endif
        @endforeach
    @endif

    @if (auth()->user()->role === 'manager')
        <h3>Assignment</h3>
        <form method="POST" action="{{ route('reports.assignments.store', $report) }}">
            @csrf
            <label for="show-service-unit-id">Unit layanan</label>
            <select id="show-service-unit-id" name="service_unit_id" required>
                <option value="">Pilih unit</option>
                @foreach ($serviceUnits as $unit)
                    <option value="{{ $unit->id }}" @selected($recommendedUnit?->id === $unit->id)>{{ $unit->name }}</option>
                @endforeach
            </select>
            <label for="show-staff-id">Petugas</label>
            <select id="show-staff-id" name="staff_id" required>
                <option value="">Pilih petugas</option>
                @foreach ($serviceUnits as $unit)
                    @foreach ($unit->staff as $staff)
                        <option value="{{ $staff->id }}">{{ $staff->name }} ({{ $unit->name }})</option>
                    @endforeach
                @endforeach
            </select>
            <label for="show-assignment-note">Catatan assignment</label>
            <textarea id="show-assignment-note" name="note" maxlength="2000"></textarea>
            <button type="submit">Tugaskan</button>
        </form>
    @endif
</section>
