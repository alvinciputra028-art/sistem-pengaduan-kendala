<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Pengaduan Kendala Rusunawa UNTAN')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand__mark" aria-hidden="true">SK</span>
                <span>
                    <strong>Sistem Pengaduan Kendala Rusunawa UNTAN</strong>
                    <small>Pelaporan kendala hunian</small>
                </span>
            </a>
            <button class="nav-toggle button button--icon" type="button" aria-expanded="false" aria-controls="primary-navigation" aria-label="Buka navigasi">
                <span class="nav-toggle__icon" aria-hidden="true">☰</span>
                <span class="sr-only">Buka navigasi</span>
            </button>
            <nav id="primary-navigation" class="site-nav" aria-label="Navigasi utama">
                <a href="{{ route('dashboard') }}" data-tooltip="Dashboard"><span class="nav-link__icon" aria-hidden="true">⌂</span><span class="nav-link__label">Dashboard</span></a>
                <a href="{{ route('reports.index') }}" data-tooltip="{{ in_array(auth()->user()->role, ['staff', 'manager'], true) ? 'Antrean' : 'Laporan' }}"><span class="nav-link__icon" aria-hidden="true">▤</span><span class="nav-link__label">{{ in_array(auth()->user()->role, ['staff', 'manager'], true) ? 'Antrean' : 'Laporan' }}</span></a>
                @if (auth()->user()->role === 'resident')
                    <a href="{{ route('community.index') }}" data-tooltip="Komunitas"><span class="nav-link__icon" aria-hidden="true">◉</span><span class="nav-link__label">Komunitas</span></a>
                @endif
                <a href="{{ route('notifications.index') }}" data-tooltip="Notifikasi" @if ($unreadNotificationCount > 0) aria-label="Notifikasi, {{ $unreadNotificationCount }} belum dibaca" @endif>
                    <span class="nav-link__icon" aria-hidden="true">♢</span><span class="nav-link__label">Notifikasi</span>
                    @if ($unreadNotificationCount > 0)
                        <span class="notification-count" aria-hidden="true">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                    @endif
                </a>
            </nav>
            <div class="site-header__account">
                <div class="account-details">
                    <span class="account-name">{{ auth()->user()->name }}</span>
                    <span class="role-badge">{{ auth()->user()->role }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button--ghost button--small" type="submit">Keluar</button>
                </form>
            </div>
        </div>
    </header>
    <button class="nav-backdrop" type="button" aria-label="Tutup navigasi" hidden></button>
    <main id="main-content" class="page-container" tabindex="-1">
        @include('components.feedback')
        @yield('content')
    </main>
</body>
</html>
