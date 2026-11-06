<section aria-labelledby="triage-heading">
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
        $priorityLabels = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'critical' => 'Kritis'];
        $transitionLabels = [
            'in_progress' => 'Mulai tangani',
            'waiting_confirmation' => 'Tandai menunggu konfirmasi penghuni',
        ];
    @endphp

    <div class="triage-summary-grid">
        <section class="card triage-summary" aria-labelledby="triage-heading">
            <div class="section-heading">
                <h2 id="triage-heading">Ringkasan operasional</h2>
                <span class="status-badge status-badge--{{ $report->status }}">{{ $statusLabels[$report->status] ?? $report->status }}</span>
            </div>
            <dl class="detail-list">
                <div><dt>Prioritas</dt><dd><span class="priority-badge priority-badge--{{ $report->priority }}">{{ $priorityLabels[$report->priority] ?? $report->priority }}</span></dd></div>
                <div><dt>Skor dampak</dt><dd>{{ $report->impact_score ?? 'Tidak tersedia' }}</dd></div>
                <div><dt>Skor urgensi</dt><dd>{{ $report->urgency_score ?? 'Tidak tersedia' }}</dd></div>
                <div><dt>Skor risiko keselamatan</dt><dd>{{ $report->safety_risk_score ?? 'Tidak tersedia' }}</dd></div>
                <div><dt>Unit layanan</dt><dd>{{ $activeAssignment?->serviceUnit?->name ?? 'Belum ditentukan' }}</dd></div>
                <div><dt>Petugas</dt><dd>{{ $activeAssignment?->staff?->name ?? 'Belum ditentukan' }}</dd></div>
            </dl>
        </section>

        <section class="card triage-summary" aria-labelledby="estimate-summary-heading">
            <h2 id="estimate-summary-heading">Estimasi penanganan</h2>
            <dl class="detail-list">
                <div><dt>Perkiraan respons</dt><dd>{{ $report->estimated_response_at?->format('d/m/Y H:i') ?? 'Belum ditentukan' }}</dd></div>
                <div><dt>Perkiraan selesai</dt><dd>{{ $report->estimated_resolution_at?->format('d/m/Y H:i') ?? 'Belum ditentukan' }}</dd></div>
                <div><dt>Catatan</dt><dd>{{ $report->estimate_note ?: 'Belum ada catatan.' }}</dd></div>
            </dl>
        </section>
    </div>

    @can('updatePriority', $report)
        <form class="card triage-form" method="POST" action="{{ route('reports.triage.update', $report) }}">
            @csrf
            @method('PATCH')
            <div class="section-heading">
                <div>
                    <h2>Perbarui triase</h2>
                    <p>Alasan wajib diisi hanya ketika nilai prioritas atau estimasi berubah.</p>
                </div>
            </div>
            <div class="form-grid">
                <div>
                    <label for="priority">Prioritas final</label>
                    <select id="priority" name="priority" required>
                        @foreach ($priorityLabels as $priority => $label)
                            <option value="{{ $priority }}" @selected(old('priority', $report->priority) === $priority)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="estimate_note">Catatan estimasi</label>
                    <input id="estimate_note" name="estimate_note" maxlength="2000" value="{{ old('estimate_note', $report->estimate_note) }}">
                </div>
                <div>
                    <label for="estimated_response_at">Estimasi respons (WIB)</label>
                    <input id="estimated_response_at" type="datetime-local" name="estimated_response_at" value="{{ old('estimated_response_at', optional($report->estimated_response_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="estimated_resolution_at">Estimasi selesai (WIB)</label>
                    <input id="estimated_resolution_at" type="datetime-local" name="estimated_resolution_at" value="{{ old('estimated_resolution_at', optional($report->estimated_resolution_at)->format('Y-m-d\TH:i')) }}">
                </div>
            </div>
            <div class="form-grid">
                <div>
                    <label for="reason">Alasan perubahan prioritas</label>
                    <textarea id="reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea>
                </div>
                <div>
                    <label for="estimate_reason">Alasan perubahan estimasi</label>
                    <textarea id="estimate_reason" name="estimate_reason" maxlength="2000">{{ old('estimate_reason') }}</textarea>
                </div>
            </div>
            <div class="form-submit"><button type="submit">Simpan triase</button></div>
        </form>
    @else
        <p class="card" role="status">Prioritas dan estimasi tidak dapat diubah setelah pekerjaan menunggu konfirmasi penghuni atau laporan ditutup.</p>
    @endcan

    @if (auth()->user()->role === 'staff')
        <section class="card triage-actions" aria-labelledby="handling-heading">
            <h2 id="handling-heading">Aksi penanganan staff</h2>
            @forelse ($availableTransitions as $status)
            @if (in_array($status, $availableTransitions, true))
                <form class="triage-action" method="POST" action="{{ route('reports.triage.status', $report) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $status }}">
                    <label for="triage-note-{{ $status }}">{{ $transitionLabels[$status] ?? $status }}</label>
                    <p class="form-helper">Catatan wajib disimpan pada histori laporan.</p>
                    <textarea id="triage-note-{{ $status }}" name="note" required maxlength="2000" placeholder="Jelaskan tindakan atau hasil penanganan"></textarea>
                    <button type="submit">{{ $transitionLabels[$status] ?? 'Perbarui status' }}</button>
                </form>
            @endif
            @empty
                <p role="status">Belum ada transisi status yang dapat dilakukan untuk laporan ini.</p>
            @endforelse
        </section>
    @endif

    @if (auth()->user()->role === 'manager')
        <section class="card triage-actions" aria-labelledby="assignment-heading">
            <h2 id="assignment-heading">Assignment manager</h2>
            <p class="form-helper">Pilih unit layanan dan petugas yang sesuai dengan gedung laporan.</p>
            <form method="POST" action="{{ route('reports.assignments.store', $report) }}">
            @csrf
            <div class="form-grid">
                <div>
                    <label for="show-service-unit-id">Unit layanan</label>
                    <select id="show-service-unit-id" name="service_unit_id" required>
                <option value="">Pilih unit</option>
                @foreach ($serviceUnits as $unit)
                    <option value="{{ $unit->id }}" @selected($activeAssignment?->service_unit_id === $unit->id || (!$activeAssignment && $recommendedUnit?->id === $unit->id))>{{ $unit->name }}</option>
                @endforeach
                    </select>
                </div>
                <div>
                    <label for="show-staff-id">Petugas</label>
                    <select id="show-staff-id" name="staff_id" required>
                <option value="">Pilih petugas</option>
                @foreach ($serviceUnits as $unit)
                    @foreach ($unit->staff as $staff)
                        <option value="{{ $staff->id }}" @selected($activeAssignment?->staff_id === $staff->id)>{{ $staff->name }} ({{ $unit->name }})</option>
                    @endforeach
                @endforeach
                    </select>
                </div>
            </div>
            <label for="show-assignment-note">Catatan assignment</label>
            <textarea id="show-assignment-note" name="note" maxlength="2000" placeholder="Catatan untuk petugas (opsional)"></textarea>
            <div class="form-submit"><button type="submit">Simpan assignment</button></div>
            </form>
        </section>
    @endif

    @if ($report->assignments->isNotEmpty())
        <section class="card" aria-labelledby="assignment-history-heading">
            <h2 id="assignment-history-heading">Riwayat assignment</h2>
            <div class="assignment-history">
                @foreach ($report->assignments->sortByDesc('assigned_at') as $assignment)
                    <article class="assignment-history__item">
                        <strong>{{ $assignment->serviceUnit?->name ?? 'Unit tidak tersedia' }} — {{ $assignment->staff?->name ?? 'Petugas tidak tersedia' }}</strong>
                        <small>{{ $assignment->assigned_at?->format('d/m/Y H:i') ?? 'Waktu tidak tersedia' }} · {{ $assignment->unassigned_at ? 'Selesai' : 'Aktif' }}</small>
                        @if ($assignment->note)<p>{{ $assignment->note }}</p>@endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</section>
