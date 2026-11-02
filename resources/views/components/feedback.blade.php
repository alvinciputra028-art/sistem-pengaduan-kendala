@if (session('success'))
    <div class="alert alert--success" role="status">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert--error" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert--error" role="alert" aria-live="assertive">
        <strong>Periksa kembali data berikut:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
