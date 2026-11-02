@extends('layouts.app')

@section('title', 'Triase '.$report->ticket_number)

@section('content')
    <p><a href="{{ route('reports.show', $report) }}">← Lihat laporan</a></p>
        <h1>Triase laporan {{ $report->ticket_number }}</h1>
        <p><strong>{{ $report->title }}</strong></p>
        @include('reports.partials.triage')
@endsection
