<div class="dashboard-report-list">
    @forelse ($reports as $report)
        <a class="dashboard-report" href="{{ route('reports.show', $report) }}">
            <span>
                <strong>{{ $report->title }}</strong>
                <small>{{ $report->ticket_number }} · {{ $report->category->name }} · {{ $report->building->name }}</small>
            </span>
            <span class="dashboard-report__meta">
                <span class="status-badge status-badge--{{ $report->status }}">{{ $statusLabels[$report->status] ?? $report->status }}</span>
                @if (isset($priorityLabels[$report->priority]))
                    <span class="priority-badge priority-badge--{{ $report->priority }}">{{ $priorityLabels[$report->priority] }}</span>
                @endif
            </span>
        </a>
    @empty
        <p class="empty-state">Tidak ada laporan untuk ditampilkan.</p>
    @endforelse
</div>
