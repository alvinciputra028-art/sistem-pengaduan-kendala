@extends('layouts.app')

@section('title', in_array(auth()->user()->role, ['staff', 'manager'], true) ? 'Antrean dan daftar laporan' : 'Daftar laporan')

@section('content')
    @php
        $isQueue = in_array(auth()->user()->role, ['staff', 'manager'], true);
        $statusLabels = [
            'submitted' => 'Dikirim',
            'triaged' => 'Ditriase',
            'assigned' => 'Ditugaskan',
            'in_progress' => 'Dikerjakan',
            'waiting_confirmation' => 'Menunggu konfirmasi',
            'reopened' => 'Dibuka kembali',
            'closed' => 'Ditutup',
        ];
        $priorityLabels = ['critical' => 'Kritis', 'high' => 'Tinggi', 'normal' => 'Normal', 'low' => 'Rendah'];
    @endphp
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ $isQueue ? 'Pusat pekerjaan' : 'Riwayat pengaduan' }}</p>
            <h1>{{ $isQueue ? 'Antrean dan daftar laporan' : 'Daftar laporan' }}</h1>
        </div>
        @if (auth()->user()->role === 'resident')
            <a class="button" href="{{ route('reports.create') }}">Buat laporan</a>
        @endif
    </div>
    <form class="filter-panel card" method="GET" action="{{ route('reports.index') }}">
        <div class="filter-panel__search">
            <label for="report-search">Cari laporan</label>
            <input id="report-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ticket, judul, atau kategori">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua status</option>
                @foreach ($statusLabels as $status => $label)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="priority">Prioritas</label>
            <select id="priority" name="priority">
                <option value="">Semua prioritas</option>
                @foreach ($priorityLabels as $priority => $label)
                    <option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if ($isQueue)
            <div>
                <label for="building_id">Gedung</label>
                <select id="building_id" name="building_id">
                    <option value="">Semua gedung</option>
                    @foreach ($buildings as $building)
                        <option value="{{ $building->id }}" @selected(($filters['building_id'] ?? null) == $building->id)>{{ $building->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="service_unit_id">Unit layanan</label>
                <select id="service_unit_id" name="service_unit_id">
                    <option value="">Semua unit</option>
                    @foreach ($serviceUnits as $unit)
                        <option value="{{ $unit->id }}" @selected(($filters['service_unit_id'] ?? null) == $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="age">Usia</label>
                <select id="age" name="age">
                    <option value="">Semua usia</option>
                    <option value="under_1_day" @selected(($filters['age'] ?? null) === 'under_1_day')>&lt; 1 hari</option>
                    <option value="1_to_3_days" @selected(($filters['age'] ?? null) === '1_to_3_days')>1-3 hari</option>
                    <option value="over_3_days" @selected(($filters['age'] ?? null) === 'over_3_days')>&gt; 3 hari</option>
                </select>
            </div>
        @endif
        <div class="filter-panel__actions">
            <button type="submit">Terapkan filter</button>
            @if (count(array_filter($filters)))
                <a class="button button--secondary" href="{{ route('reports.index') }}">Reset</a>
            @endif
        </div>
    </form>
    @if ($reports->isEmpty())
        <section class="card empty-state">
            <h2>{{ count(array_filter($filters)) ? 'Laporan tidak ditemukan' : ($isQueue ? 'Antrean masih kosong' : 'Belum ada laporan') }}</h2>
            <p>{{ count(array_filter($filters)) ? 'Coba ubah kata kunci atau filter yang digunakan.' : ($isQueue ? 'Belum ada pekerjaan yang sesuai dengan akses Anda.' : 'Laporan yang Anda buat akan muncul di sini.') }}</p>
            @if (count(array_filter($filters)))
                <a class="button button--secondary" href="{{ route('reports.index') }}">Hapus filter</a>
            @endif
        </section>
    @endif
        @forelse ($reports as $report)
            <article class="report-list-item">
                <div class="report-list-item__header">
                    <div>
                        <p class="report-ticket">{{ $report->ticket_number }}</p>
                        <h2><a href="{{ route('reports.show', $report) }}">{{ $report->title }}</a></h2>
                    </div>
                    <div class="report-badges">
                        <span class="status-badge status-badge--{{ $report->status }}">{{ $statusLabels[$report->status] ?? $report->status }}</span>
                        <span class="priority-badge priority-badge--{{ $report->priority }}">{{ $priorityLabels[$report->priority] ?? $report->priority }}</span>
                    </div>
                </div>
                <div class="report-list-item__meta">
                    <span>{{ $report->category->name }}</span>
                    <span>{{ $report->building->name }}</span>
                    <span>Dibuat {{ $report->created_at->diffForHumans() }}</span>
                </div>
                @if ($isQueue)
                    @if ($report->assignments->first())
                        <p class="report-assignment">Assignment: {{ $report->assignments->first()->serviceUnit->name }} — {{ $report->assignments->first()->staff?->name ?? 'Belum ada petugas' }}</p>
                    @else
                        <p class="report-assignment">Assignment: Belum ditugaskan</p>
                    @endif
                    @if ($report->estimated_resolution_at)
                        <p class="report-estimate">Estimasi selesai: {{ $report->estimated_resolution_at->format('d/m/Y H:i') }}</p>
                    @endif
                    @if (auth()->user()->role === 'staff' && $report->reporter_id !== auth()->id())
                        <div class="moderation-actions">
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
                        </div>
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
                <a class="report-list-item__link" href="{{ route('reports.show', $report) }}">Lihat detail laporan</a>
            </article>
        @empty
            <p>Belum ada laporan yang dapat ditampilkan.</p>
        @endforelse
        <div class="pagination">{{ $reports->links() }}</div>
@endsection
