@extends('layouts.app')

@section('title', 'Dashboard - Sistem Pengaduan')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Ringkasan aktivitas</p>
        <h1>Dashboard</h1>
        </div>
    </div>
    <section class="card stack">
        <p>Selamat datang, {{ $user->name }}.</p>
        <p>Role: <strong>{{ $user->role }}</strong></p>
        <p><a href="{{ route('reports.index') }}">{{ in_array($user->role, ['staff', 'manager'], true) ? 'Lihat antrean' : 'Lihat laporan' }}</a></p>
        <p><a href="{{ route('notifications.index') }}">Notifikasi</a></p>
        @if ($user->role === 'resident')
            <p><a href="{{ route('community.index') }}">Komunitas</a></p>
        @endif
        @if ($user->role === 'manager')
            <p><a href="{{ route('community.moderation') }}">Moderasi komunitas</a></p>
        @endif
        @if ($user->role === 'resident')
            <p><a href="{{ route('reports.create') }}">Buat laporan baru</a></p>
        @endif

    </section>
@endsection
