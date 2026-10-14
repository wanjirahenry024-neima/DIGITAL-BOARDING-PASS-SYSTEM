@extends('layouts.app')

@section('title', 'Check in ' . $booking->booking_reference . ' | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2"><a href="{{ route('bookings.index') }}">My bookings</a> / Check in</p>
        <h1 class="h2 mb-1">Check in for your flight</h1>
        <p class="text-muted-custom mb-4">Booking reference {{ $booking->booking_reference }}</p>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="content-panel p-4 p-md-5">
            <h2 class="h5 mb-3">Passenger</h2>
            <dl class="row">
                <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $booking->passenger->full_name }}</dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $booking->passenger->email }}</dd>
                <dt class="col-sm-4">Passengers on booking</dt><dd class="col-sm-8">{{ $booking->number_of_passengers }}</dd>
            </dl>

            <h2 class="h5 mt-4 mb-3">Flight</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Flight</dt><dd class="col-sm-8">{{ $booking->flight->flight_number }} · {{ $booking->flight->airline }}</dd>
                <dt class="col-sm-4">Route</dt><dd class="col-sm-8">{{ $booking->flight->departure_airport }} &rarr; {{ $booking->flight->arrival_airport }}</dd>
                <dt class="col-sm-4">Departure</dt><dd class="col-sm-8">{{ $booking->flight->departure_date->format('M j, Y') }} at {{ substr($booking->flight->departure_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Booking status</dt><dd class="col-sm-8">{{ $booking->booking_status }}</dd>
            </dl>
        </div>

        @if ($booking->checkIn)
            <div class="alert alert-info mt-4" role="status">
                This booking has already been checked in.
                @if ($booking->checkIn->boardingPass)
                    <a href="{{ route('boarding-passes.show', $booking->checkIn->boardingPass) }}">View boarding pass</a>
                @else
                    <a href="{{ route('check-ins.confirmation', $booking->checkIn) }}">View check-in confirmation</a>
                @endif
            </div>
        @elseif ($unavailableReason)
            <div class="alert alert-warning mt-4" role="status">{{ $unavailableReason }}</div>
        @else
            <form class="content-panel p-4 mt-4" method="POST" action="{{ route('check-ins.store', $booking) }}">
                @csrf
                <div class="form-check">
                    <input class="form-check-input" id="confirmation" name="confirmation" type="checkbox" value="1" required>
                    <label class="form-check-label" for="confirmation">I confirm that the passenger and flight details are correct.</label>
                </div>
                <button class="btn btn-primary mt-4" type="submit">Confirm check-in</button>
            </form>
        @endif
    </section>
@endsection
