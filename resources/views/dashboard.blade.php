@extends('layouts.app')

@section('title', 'Dashboard - Sistem Pengaduan Kendala Rusunawa UNTAN')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Ringkasan aktivitas</p>
            <h1>Dashboard</h1>
            <p class="form-helper">Selamat datang, {{ $user->name }}. Berikut ringkasan pekerjaan Anda.</p>
        </div>
    </div>
    @php
        $statusLabels = [
            'submitted' => 'Dikirim',
            'triaged' => 'Ditriase',
            'assigned' => 'Ditugaskan',
            'in_progress' => 'Dikerjakan',
            'waiting_confirmation' => 'Menunggu konfirmasi',
            'reopened' => 'Dibuka kembali',
            'closed' => 'Ditutup',
        ];
        $priorityLabels = ['critical' => 'Kritis', 'high' => 'Tinggi'];
    @endphp

    <section class="dashboard-grid" aria-label="Ringkasan laporan">
        <article class="card dashboard-card">
            <span class="dashboard-card__label">Total laporan</span>
            <strong class="dashboard-card__value">{{ array_sum($reportCounts) }}</strong>
            <small>{{ $user->role === 'resident' ? 'Laporan Anda' : ($user->role === 'staff' ? 'Assignment aktif' : 'Seluruh sistem') }}</small>
        </article>
        @if ($user->role === 'resident')
            <article class="card dashboard-card">
                <span class="dashboard-card__label">Menunggu konfirmasi</span>
                <strong class="dashboard-card__value">{{ $waitingConfirmationCount }}</strong>
                <small>Laporan yang menunggu tindakan Anda</small>
            </article>
        @elseif ($user->role === 'staff')
            <article class="card dashboard-card">
                <span class="dashboard-card__label">Perlu ditangani</span>
                <strong class="dashboard-card__value">{{ ($reportCounts['assigned'] ?? 0) + ($reportCounts['in_progress'] ?? 0) + ($reportCounts['reopened'] ?? 0) }}</strong>
                <small>Assignment yang belum selesai</small>
            </article>
        @else
            <article class="card dashboard-card">
                <span class="dashboard-card__label">Menunggu triase</span>
                <strong class="dashboard-card__value">{{ $triageCount }}</strong>
                <small>Laporan baru untuk ditinjau</small>
            </article>
            <article class="card dashboard-card">
                <span class="dashboard-card__label">Moderasi terbuka</span>
                <strong class="dashboard-card__value">{{ $moderationCount }}</strong>
                <small>Flag komunitas yang menunggu</small>
            </article>
        @endif
    </section>

    @if (count($reportCounts) > 0)
        <section class="card dashboard-section">
            <div class="section-heading">
                <h2>Status laporan</h2>
                <a class="button button--secondary button--small" href="{{ route('reports.index') }}">{{ $user->role === 'resident' ? 'Lihat laporan' : 'Buka antrean' }}</a>
            </div>
            <div class="status-list">
                @foreach ($statusLabels as $status => $label)
                    @if (($reportCounts[$status] ?? 0) > 0)
                        <div class="status-list__item">
                            <span>{{ $label }}</span>
                            <strong>{{ $reportCounts[$status] }}</strong>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if ($user->role === 'manager' && $priorityReports->isNotEmpty())
        <section class="card dashboard-section">
            <div class="section-heading">
                <h2>Prioritas tinggi dan kritis</h2>
                <a class="button button--secondary button--small" href="{{ route('reports.index') }}">Lihat antrean</a>
            </div>
            @include('dashboard-report-list', ['reports' => $priorityReports, 'priorityLabels' => $priorityLabels])
        </section>
    @elseif ($user->role !== 'manager' && $recentReports->isNotEmpty())
        <section class="card dashboard-section">
            <div class="section-heading">
                <h2>{{ $user->role === 'staff' ? 'Assignment yang perlu ditangani' : 'Laporan terbaru' }}</h2>
                <a class="button button--secondary button--small" href="{{ route('reports.index') }}">Lihat semua</a>
            </div>
            @include('dashboard-report-list', ['reports' => $recentReports, 'priorityLabels' => $priorityLabels])
        </section>
    @endif

    @if ($user->role === 'resident' && $recentReports->isEmpty())
        <section class="card empty-state">
            <h2>Belum ada laporan</h2>
            <p>Laporkan kendala hunian agar dapat segera ditindaklanjuti.</p>
        </section>
    @elseif ($user->role === 'staff' && $recentReports->isEmpty())
        <section class="card empty-state">
            <h2>Tidak ada assignment aktif</h2>
            <p>Belum ada laporan yang ditugaskan kepada Anda.</p>
        </section>
    @endif

    <div class="dashboard-actions">
        @if ($user->role === 'resident')
            <a class="button" href="{{ route('reports.create') }}">Buat laporan baru</a>
            <a class="button button--secondary" href="{{ route('community.index') }}">Buka komunitas</a>
        @elseif ($user->role === 'manager')
            <a class="button" href="{{ route('reports.index') }}">Buka antrean laporan</a>
            <a class="button button--secondary" href="{{ route('community.moderation') }}">Buka moderasi</a>
        @else
            <a class="button" href="{{ route('reports.queue') }}">Buka antrean saya</a>
        @endif
        <a class="button button--ghost" href="{{ route('notifications.index') }}">Lihat notifikasi</a>
    </div>
@endsection
