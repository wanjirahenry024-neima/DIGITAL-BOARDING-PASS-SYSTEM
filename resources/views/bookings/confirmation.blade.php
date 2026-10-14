@extends('layouts.app')

@section('title', 'Booking confirmed | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2">Booking confirmed</p>
        <h1 class="h2 mb-2">Your seats are reserved.</h1>
        <p class="text-muted-custom mb-4">Keep your booking reference for your trip.</p>
        <div class="content-panel p-4 p-md-5">
            <p class="mono-label mb-1">Booking reference</p>
            <p class="h3 mb-4">{{ $booking->booking_reference }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-4">Flight</dt><dd class="col-sm-8">{{ $booking->flight->flight_number }} · {{ $booking->flight->airline }}</dd>
                <dt class="col-sm-4">Route</dt><dd class="col-sm-8">{{ $booking->flight->departure_airport }} &rarr; {{ $booking->flight->arrival_airport }}</dd>
                <dt class="col-sm-4">Departure</dt><dd class="col-sm-8">{{ $booking->flight->departure_date->format('M j, Y') }} at {{ substr($booking->flight->departure_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Passengers</dt><dd class="col-sm-8">{{ $booking->number_of_passengers }}</dd>
                <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $booking->booking_status }}</dd>
            </dl>
        </div>
        <a class="btn btn-primary mt-4" href="{{ route('bookings.show', $booking) }}">View booking</a>
    </section>
@endsection