@extends('layouts.app')

@section('title', 'Triase '.$report->ticket_number)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Pusat pekerjaan</p>
            <h1>Triase laporan {{ $report->ticket_number }}</h1>
            <p class="form-context">{{ $report->title }}</p>
        </div>
        <a class="button button--secondary button--small" href="{{ route('reports.show', $report) }}">Ke laporan</a>
    </div>
    @include('reports.partials.triage')
@endsection
