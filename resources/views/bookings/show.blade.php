@extends('layouts.app')

@section('title', 'Booking ' . $booking->booking_reference . ' | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2"><a href="{{ route('bookings.index') }}">My bookings</a> / Booking details</p>
        <h1 class="h2 mb-1">{{ $booking->booking_reference }}</h1>
        <p class="text-muted-custom mb-4">{{ $booking->booking_status }}</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="content-panel p-4 p-md-5">
            <dl class="row mb-0">
                <dt class="col-sm-4">Flight</dt><dd class="col-sm-8">{{ $booking->flight->flight_number }} · {{ $booking->flight->airline }}</dd>
                <dt class="col-sm-4">Route</dt><dd class="col-sm-8">{{ $booking->flight->departure_airport }} &rarr; {{ $booking->flight->arrival_airport }}</dd>
                <dt class="col-sm-4">Departure</dt><dd class="col-sm-8">{{ $booking->flight->departure_date->format('M j, Y') }} at {{ substr($booking->flight->departure_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Passengers</dt><dd class="col-sm-8">{{ $booking->number_of_passengers }}</dd>
                <dt class="col-sm-4">Booked on</dt><dd class="col-sm-8">{{ $booking->booking_date->format('M j, Y g:i A') }}</dd>
            </dl>
        </div>

        @if ($checkIn?->boardingPass)
            <a class="btn btn-primary mt-4" href="{{ route('boarding-passes.show', $checkIn->boardingPass) }}">View boarding pass</a>
        @elseif ($checkIn)
            <a class="btn btn-primary mt-4" href="{{ route('check-ins.confirmation', $checkIn) }}">View check-in confirmation</a>
        @elseif ($canCheckIn)
            <a class="btn btn-primary mt-4" href="{{ route('check-ins.create', $booking) }}">Check in</a>
        @endif

        @if ($canCancel)
            <form class="mt-4" method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?')">
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-danger" type="submit">Cancel booking</button>
            </form>
        @endif
    </section>
@endsection