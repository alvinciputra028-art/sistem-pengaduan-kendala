<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistem Pengaduan Kendala Rusunawa UNTAN')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-shell">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <main id="main-content" class="guest-container" tabindex="-1">
        @yield('content')
    </main>
</body>
</html>
