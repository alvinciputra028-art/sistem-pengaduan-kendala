<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Triase {{ $report->ticket_number }}</title>
</head>
<body>
    <main>
        <p><a href="{{ route('reports.show', $report) }}">Lihat laporan</a></p>
        <h1>Triase laporan {{ $report->ticket_number }}</h1>
        @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
        @if ($errors->any())
            <div role="alert">
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <p><strong>{{ $report->title }}</strong></p>
        @include('reports.partials.triage')
    </main>
</body>
</html>
