@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Pembaruan sistem</p>
        <h1>Notifikasi</h1>
        </div>
    </div>
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
        <div class="pagination">{{ $notifications->links() }}</div>
@endsection
