@extends('layouts.app')

@section('title', 'Check-in confirmed | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2">Check-in complete</p>
        <h1 class="h2 mb-2">You are checked in.</h1>
        <p class="text-muted-custom mb-4">Your seat has been assigned. Keep this reference for your trip.</p>

        <div class="content-panel p-4 p-md-5">
            <p class="mono-label mb-1">Check-in reference</p>
            <p class="h3 mb-4">{{ $checkIn->check_in_reference }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-4">Passenger</dt><dd class="col-sm-8">{{ $checkIn->passenger->full_name }} · {{ $checkIn->passenger->email }}</dd>
                <dt class="col-sm-4">Flight</dt><dd class="col-sm-8">{{ $checkIn->flight->flight_number }} · {{ $checkIn->flight->airline }}</dd>
                <dt class="col-sm-4">Route</dt><dd class="col-sm-8">{{ $checkIn->flight->departure_airport }} &rarr; {{ $checkIn->flight->arrival_airport }}</dd>
                <dt class="col-sm-4">Departure</dt><dd class="col-sm-8">{{ $checkIn->flight->departure_date->format('M j, Y') }} at {{ substr($checkIn->flight->departure_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Seat</dt><dd class="col-sm-8">{{ $checkIn->seat_number }}</dd>
                <dt class="col-sm-4">Checked in</dt><dd class="col-sm-8">{{ $checkIn->checked_in_at->format('M j, Y g:i A') }}</dd>
                <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $checkIn->status }}</dd>
            </dl>
        </div>

        @if ($checkIn->boardingPass)
            <a class="btn btn-primary mt-4" href="{{ route('boarding-passes.show', $checkIn->boardingPass) }}">View your boarding pass</a>
        @endif
        <a class="btn btn-outline-primary" href="{{ route('bookings.show', $checkIn->booking) }}">View booking</a>
    </section>
@endsection
