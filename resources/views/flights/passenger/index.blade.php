@extends('layouts.app')

@section('title', 'Search flights | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="mb-4">
            <p class="eyebrow mb-2">Passenger travel</p>
            <h1 class="h2 mb-1">Find a flight</h1>
            <p class="text-muted-custom mb-0">Browse upcoming flights with seats available.</p>
        </div>

        <form class="content-panel p-3 mb-4" method="GET" action="{{ route('flights.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="departure_airport">From</label>
                    <input class="form-control" id="departure_airport" name="departure_airport" value="{{ $filters['departure_airport'] ?? '' }}" placeholder="Airport code or name">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="arrival_airport">To</label>
                    <input class="form-control" id="arrival_airport" name="arrival_airport" value="{{ $filters['arrival_airport'] ?? '' }}" placeholder="Airport code or name">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="departure_date">Travel date</label>
                    <input class="form-control" id="departure_date" name="departure_date" type="date" min="{{ now()->toDateString() }}" value="{{ $filters['departure_date'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Search</button>
                    <a class="btn btn-outline-secondary" href="{{ route('flights.index') }}" aria-label="Clear search">Clear</a>
                </div>
            </div>
        </form>

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th scope="col">Flight</th><th scope="col">Route</th><th scope="col">Departure</th><th scope="col">Seats</th><th scope="col"><span class="visually-hidden">Details</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($flights as $flight)
                        <tr>
                            <td><span class="fw-semibold">{{ $flight->flight_number }}</span><span class="d-block small text-muted-custom">{{ $flight->airline }}</span></td>
                            <td>{{ $flight->departure_airport }} &rarr; {{ $flight->arrival_airport }}</td>
                            <td>{{ $flight->departure_date->format('M j, Y') }}<span class="d-block small text-muted-custom">{{ substr($flight->departure_time, 0, 5) }}</span></td>
                            <td>{{ $flight->available_seats }} available</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('flights.show', $flight) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="5">No available flights match those details.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $flights->links() }}</div>
    </section>
@endsection