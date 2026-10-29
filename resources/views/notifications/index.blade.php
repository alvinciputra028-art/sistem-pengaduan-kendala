<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi</title>
</head>
<body>
    <main>
        <p><a href="{{ route('dashboard') }}">Dashboard</a></p>
        <h1>Notifikasi</h1>
        @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
        @forelse ($notifications as $notification)
            <article>
                <p @if (!$notification->read_at)><strong>@endif>
                    {{ $notification->data['message'] ?? $notification->type }}
                    @if (!$notification->read_at)</strong>@endif
                </p>
                <p>{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                @if (isset($notification->data['report_id']))
                    <a href="{{ route('notifications.open', $notification) }}">
                        Ke laporan
                    </a>
                @elseif (!$notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Tandai sudah dibaca</button>
                    </form>
                @endif
            </article>
        @empty
            <p>Belum ada notifikasi.</p>
        @endforelse
        {{ $notifications->links() }}
    </main>
</body>
</html>
