@extends('layouts.app')

@section('title', $flight->flight_number . ' | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <p class="eyebrow mb-2"><a href="{{ route('admin.flights.index') }}">Flights</a> / Flight details</p>
                <h1 class="h2 mb-1">{{ $flight->flight_number }}</h1>
                <p class="text-muted-custom mb-0">{{ $flight->airline }} · {{ $flight->status }}</p>
            </div>
            <a class="btn btn-primary" href="{{ route('admin.flights.edit', $flight) }}">Edit flight</a>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="content-panel p-4 p-md-5">
            <dl class="row mb-0">
                <dt class="col-sm-4">Route</dt><dd class="col-sm-8">{{ $flight->departure_airport }} &rarr; {{ $flight->arrival_airport }}</dd>
                <dt class="col-sm-4">Departure</dt><dd class="col-sm-8">{{ $flight->departure_date->format('M j, Y') }} at {{ substr($flight->departure_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Arrival time</dt><dd class="col-sm-8">{{ substr($flight->arrival_time, 0, 5) }}</dd>
                <dt class="col-sm-4">Aircraft</dt><dd class="col-sm-8">{{ $flight->aircraft ?: 'Not specified' }}</dd>
                <dt class="col-sm-4">Available seats</dt><dd class="col-sm-8">{{ $flight->available_seats }} of {{ $flight->total_seats }}</dd>
            </dl>
        </div>

        <form class="content-panel p-3 mt-4" method="POST" action="{{ route('admin.flights.status', $flight) }}">
            @csrf
            @method('PATCH')
            <div class="row g-2 align-items-end">
                <div class="col-sm-5">
                    <label class="form-label" for="status">Change status</label>
                    <select class="form-select" id="status" name="status" required>
                        @foreach (\App\Models\Flight::STATUSES as $status)
                            <option value="{{ $status }}" @selected($flight->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-auto"><button class="btn btn-outline-primary" type="submit">Update status</button></div>
            </div>
        </form>
    </section>
@endsection