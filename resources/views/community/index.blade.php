@extends('layouts.app')

@section('title', 'Komunitas')

@section('content')
    @php
        $statusLabels = ['submitted' => 'Dikirim', 'triaged' => 'Ditriase', 'assigned' => 'Ditugaskan', 'in_progress' => 'Dikerjakan', 'waiting_confirmation' => 'Menunggu konfirmasi', 'reopened' => 'Dibuka kembali'];
    @endphp
    <div class="page-heading">
        <div>
            <p class="eyebrow">Ruang bersama</p>
        <h1>Komunitas gedung</h1>
        </div>
    </div>
        <div class="report-card-grid">
        @forelse ($reports as $report)
            <article class="community-card">
                <div class="report-list-item__header">
                    <div>
                        <p class="report-ticket">{{ $report->ticket_number }}</p>
                        <h2><a href="{{ route('reports.show', $report) }}">{{ $report->title }}</a></h2>
                    </div>
                    <span class="status-badge status-badge--{{ $report->status }}">{{ $statusLabels[$report->status] ?? $report->status }}</span>
                </div>
                <p class="report-list-item__meta"><span>{{ $report->building->name }}</span><span>{{ $report->category->name }}</span><span>{{ $report->acknowledgements_count }} penghuni juga mengalami</span></p>
                <p class="community-description">{{ $report->description }}</p>
                <p><a href="{{ route('reports.show', $report) }}">Buka percakapan komunitas</a></p>
                @if (auth()->user()->role === 'resident' && $report->reporter_id !== auth()->id() && !$report->acknowledgements->contains('user_id', auth()->id()))
                    <form method="POST" action="{{ route('community.acknowledge', $report) }}">
                        @csrf
                        <button class="button button--success button--small" type="submit">Saya juga mengalami</button>
                    </form>
                @elseif (auth()->user()->role === 'resident' && $report->reporter_id === auth()->id())
                    <p>Anda tidak dapat menandai laporan sendiri.</p>
                @elseif (auth()->user()->role === 'resident')
                    <p>Anda sudah menandai laporan ini.</p>
                @endif
                @if (auth()->user()->role === 'resident' && $report->reporter_id !== auth()->id())
                    <div class="moderation-actions">
                        <div class="moderation-action-buttons">
                            <button class="button button--warning button--small report-toggle" type="button" aria-expanded="false" aria-controls="community-report-{{ $report->id }}">Laporkan</button>
                            <form method="POST" action="{{ route('community.duplicate', $report) }}">
                                @csrf
                                <button class="button button--secondary button--small" type="submit">Tandai duplikat</button>
                            </form>
                        </div>
                        <form id="community-report-{{ $report->id }}" class="report-reason-form" method="POST" action="{{ route('community.report', $report) }}" hidden>
                            @csrf
                            <label for="reason-{{ $report->id }}">Laporkan konten</label>
                            <select id="reason-{{ $report->id }}" name="reason" required>
                                <option value="">Pilih alasan</option>
                                <option value="inappropriate">Konten tidak pantas</option>
                                <option value="personal_data">Data pribadi</option>
                                <option value="other">Lainnya</option>
                            </select>
                            <input name="note" maxlength="2000" placeholder="Catatan (opsional)">
                            <button class="button button--warning button--small" type="submit">Kirim laporan</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <p>Belum ada laporan publik di gedung ini.</p>
        @endforelse
        </div>
        <div class="pagination">{{ $reports->links() }}</div>
@endsection
