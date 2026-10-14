@extends('layouts.app')

@section('title', 'Boarding pass ' . $boardingPass->pass_reference . ' | ' . config('app.name'))

@section('content')
    <style>
        .pass-details > div { display: grid; grid-template-columns: minmax(10rem, 1fr) 2fr; gap: 1rem; padding: .65rem 0; border-bottom: 1px solid #e4e7eb; }
        .pass-details dt { color: #596579; font-weight: 600; }
        .pass-details dd { margin: 0; font-weight: 600; }
        .boarding-pass-qr { display: block; width: 12.5rem; height: 12.5rem; margin: 1.5rem auto 0; }
        @media print {
            .site-nav, footer, .pass-actions { display: none !important; }
            .page-wrap { max-width: 100% !important; padding: 0 !important; }
            .content-panel { box-shadow: none !important; border: 1px solid #8993a3 !important; }
        }
    </style>

    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2"><a href="{{ route('bookings.show', $boardingPass->booking) }}">Booking details</a> / Boarding pass</p>
        <h1 class="h2 mb-1">Digital boarding pass</h1>
        <p class="text-muted-custom mb-4">Present this pass when boarding your flight.</p>

        <div class="content-panel p-4 p-md-5">
            @include('boarding-passes._details')
        </div>

        <div class="pass-actions d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-primary" type="button" onclick="window.print()">Print / Save as PDF</button>
            <a class="btn btn-outline-primary" href="{{ route('boarding-passes.download', $boardingPass) }}">Download boarding pass</a>
            <a class="btn btn-outline-secondary" href="{{ route('bookings.show', $boardingPass->booking) }}">View booking</a>
        </div>
    </section>
@endsection
