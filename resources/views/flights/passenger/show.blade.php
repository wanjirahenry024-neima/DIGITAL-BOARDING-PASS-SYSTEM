@extends('layouts.app')

@section('title', $flight->flight_number . ' | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2"><a href="{{ route('flights.index') }}">Search flights</a> / Flight details</p>
        <h1 class="h2 mb-1">{{ $flight->flight_number }}</h1>
        <p class="text-muted-custom mb-4">{{ $flight->airline }} · {{ $flight->status }}</p>
        <div class="content-panel p-4 p-md-5">
            <dl class="row mb-0">
                <dt class="col-sm-4">Route</dt><dd class="col-sm-8">{{ $flight->departure_airport }} &rarr; {{ $flight->arrival_airport }}</dd>
                <dt class="col-sm-4">Departure</dt><dd class="col-sm-8">{{ $flight->departure_date->format('M j, Y') }} at {{ substr($flight->departure_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Arrival time</dt><dd class="col-sm-8">{{ substr($flight->arrival_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Aircraft</dt><dd class="col-sm-8">{{ $flight->aircraft ?: 'Not specified' }}</dd>
                <dt class="col-sm-4">Available seats</dt><dd class="col-sm-8">{{ $flight->available_seats }}</dd>
            </dl>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-4" role="alert">{{ $errors->first() }}</div>
        @endif

        <form class="content-panel p-4 mt-4" method="POST" action="{{ route('bookings.store') }}">
            @csrf
            <input type="hidden" name="flight_id" value="{{ $flight->id }}">
            <div class="row align-items-end g-3">
                <div class="col-sm-4">
                    <label class="form-label" for="number_of_passengers">Passengers</label>
                    <input class="form-control @error('number_of_passengers') is-invalid @enderror" id="number_of_passengers" name="number_of_passengers" type="number" min="1" max="{{ min(9, $flight->available_seats) }}" value="{{ old('number_of_passengers', 1) }}" required>
                    @error('number_of_passengers') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-auto"><button class="btn btn-primary" type="submit" @disabled($flight->available_seats < 1)>Book this flight</button></div>
            </div>
            @error('flight_id') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
        </form>
    </section>
@endsection