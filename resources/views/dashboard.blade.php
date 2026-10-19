<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Pengaduan</title>
</head>
<body>
    <main>
        <h1>Dashboard</h1>
        <p>Selamat datang, {{ $user->name }}.</p>
        <p>Role: <strong>{{ $user->role }}</strong></p>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </main>
</body>
</html>
