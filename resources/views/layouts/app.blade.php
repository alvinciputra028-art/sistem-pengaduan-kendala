<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Pengaduan Kendala')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell">
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand__mark" aria-hidden="true">SK</span>
                <span>
                    <strong>Sistem Pengaduan</strong>
                    <small>Kendala hunian</small>
                </span>
            </a>
            <nav class="site-nav" aria-label="Navigasi utama">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('reports.index') }}">{{ in_array(auth()->user()->role, ['staff', 'manager'], true) ? 'Antrean' : 'Laporan' }}</a>
                @if (auth()->user()->role === 'resident')
                    <a href="{{ route('community.index') }}">Komunitas</a>
                @endif
                @if (auth()->user()->role === 'manager')
                    <a href="{{ route('community.moderation') }}">Moderasi</a>
                @endif
                <a href="{{ route('notifications.index') }}">Notifikasi</a>
            </nav>
            <div class="site-header__account">
                <span class="account-name">{{ auth()->user()->name }}</span>
                <span class="role-badge">{{ auth()->user()->role }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button--ghost button--small" type="submit">Keluar</button>
                </form>
            </div>
        </div>
    </header>
    <main class="page-container">
        @include('components.feedback')
        @yield('content')
    </main>
</body>
</html>
