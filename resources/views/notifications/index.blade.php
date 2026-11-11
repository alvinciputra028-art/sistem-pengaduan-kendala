@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Pembaruan sistem</p>
            <h1>Notifikasi</h1>
            <p class="form-helper">Pembaruan yang relevan dengan laporan dan pekerjaan Anda.</p>
        </div>
    </div>
    @php
        $notificationLabels = [
            'report.comment_added' => 'Komentar laporan',
            'report.community_reported' => 'Moderasi komunitas',
            'report.duplicate_reported' => 'Laporan duplikat',
            'report.status_changed' => 'Perubahan status',
            'report.assigned' => 'Assignment laporan',
            'report.community_comment_reported' => 'Komentar dilaporkan',
        ];
    @endphp
    @forelse ($notifications as $notification)
        <article class="notification-item {{ $notification->read_at ? 'notification-item--read' : 'notification-item--unread' }}">
            <div class="notification-item__header">
                <span class="notification-type">{{ $notificationLabels[$notification->type] ?? 'Pembaruan laporan' }}</span>
                @if (!$notification->read_at)
                    <span class="status-badge status-badge--submitted">Belum dibaca</span>
                @else
                    <span class="notification-read-state">Sudah dibaca</span>
                @endif
            </div>
            <p class="notification-item__message">{{ $notification->data['message'] ?? 'Ada pembaruan pada laporan Anda.' }}</p>
            <p class="notification-item__meta">
                <time datetime="{{ $notification->created_at->toISOString() }}">{{ $notification->created_at->diffForHumans() }}</time>
                @if (isset($notification->data['ticket_number']))
                    · Tiket {{ $notification->data['ticket_number'] }}
                @endif
            </p>
            <div class="notification-item__actions">
                @if (isset($notification->data['report_id']))
                    <a class="button button--secondary button--small" href="{{ route('notifications.open', $notification) }}">Ke laporan</a>
                @endif
                @if (!$notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                        @csrf
                        @method('PATCH')
                        <button class="button button--ghost button--small" type="submit" data-loading-label="Menyimpan...">Tandai sudah dibaca</button>
                    </form>
                @endif
            </div>
        </article>
        @empty
            <section class="card empty-state">
                <h2>Belum ada notifikasi</h2>
                <p>Pembaruan tentang laporan Anda akan muncul di sini.</p>
            </section>
        @endforelse
    <div class="pagination">{{ $notifications->links() }}</div>
@endsection
