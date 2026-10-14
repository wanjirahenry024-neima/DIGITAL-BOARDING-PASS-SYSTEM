@extends('layouts.app')

@section('title', 'Manage flights | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <p class="eyebrow mb-2">Administration</p>
                <h1 class="h2 mb-0">Flights</h1>
            </div>
            <a class="btn btn-primary" href="{{ route('admin.flights.create') }}">Add flight</a>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form class="content-panel p-3 mb-4" method="GET" action="{{ route('admin.flights.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="q">Search flights</label>
                    <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Flight number, airline, or airport">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="departure_date">Departure date</label>
                    <input class="form-control" id="departure_date" name="departure_date" type="date" value="{{ request('departure_date') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.flights.index') }}" aria-label="Clear filters">Clear</a>
                </div>
            </div>
        </form>

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Flight</th>
                        <th scope="col">Route</th>
                        <th scope="col">Departure</th>
                        <th scope="col">Seats</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($flights as $flight)
                        <tr>
                            <td>
                                <a class="fw-semibold" href="{{ route('admin.flights.show', $flight) }}">{{ $flight->flight_number }}</a>
                                <span class="d-block small text-muted-custom">{{ $flight->airline }}</span>
                            </td>
                            <td>{{ $flight->departure_airport }} <span aria-hidden="true">&rarr;</span> {{ $flight->arrival_airport }}</td>
                            <td>{{ $flight->departure_date->format('M j, Y') }}<span class="d-block small text-muted-custom">{{ substr($flight->departure_time, 0, 5) }}</span></td>
                            <td>{{ $flight->available_seats }} / {{ $flight->total_seats }}</td>
                            <td>{{ $flight->status }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.flights.edit', $flight) }}">Edit</a>
                                <form class="d-inline" method="POST" action="{{ route('admin.flights.destroy', $flight) }}" onsubmit="return confirm('Deactivate this flight?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="6">No flights match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $flights->links() }}</div>
    </section>
@endsection