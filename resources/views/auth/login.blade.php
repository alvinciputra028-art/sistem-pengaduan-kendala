<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Sistem Pengaduan</title>
</head>
<body>
    <main>
        <h1>Masuk</h1>

        @if ($errors->any())
            <div role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <label>
                <input type="checkbox" name="remember" value="1">
                Ingat saya
            </label>
            <button type="submit">Masuk</button>
        </form>

        @if (app()->environment('local'))
            <p>Akun development:</p>
            <ul>
                <li>Penghuni: <code>resident@example.test</code></li>
                <li>Teman sekamar: <code>resident-roommate@example.test</code></li>
                <li>Penghuni gedung Putra: <code>resident-putra@example.test</code></li>
                <li>Penghuni gedung Putri: <code>resident-putri@example.test</code></li>
                <li>Petugas: <code>staff@example.test</code></li>
                <li>Petugas pemeliharaan: <code>staff-maintenance@example.test</code></li>
                <li>Petugas keamanan: <code>staff-security@example.test</code></li>
                <li>Manajer: <code>manager@example.test</code></li>
                <li>Password: <code>password</code></li>
            </ul>
        @endif
    </main>
</body>
</html>
