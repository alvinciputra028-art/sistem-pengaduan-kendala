@extends('layouts.guest')

@section('title', 'Masuk - Sistem Pengaduan Kendala Rusunawa UNTAN')

@section('content')
    <section class="card">
        <p class="eyebrow">Sistem Pengaduan Kendala Rusunawa UNTAN</p>
        <h1>Masuk</h1>
        <p class="form-helper">Gunakan akun Anda untuk melanjutkan ke dashboard.</p>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="email-error" @endif>
                @error('email')
                    <p id="email-error" class="field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif>
                @error('password')
                    <p id="password-error" class="field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <label class="checkbox-label">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Ingat saya</span>
            </label>
            <button class="button" type="submit">Masuk ke aplikasi</button>
        </form>

        @if (app()->environment('local'))
            <details class="development-credentials">
                <summary>Akun development (lokal)</summary>
                <p class="form-helper">Hanya tampil di environment lokal.</p>
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
            </details>
        @endif
    </section>
@endsection
