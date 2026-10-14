@extends('layouts.app')

@section('title', 'My bookings | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="mb-4">
            <p class="eyebrow mb-2">Passenger travel</p>
            <h1 class="h2 mb-0">My bookings</h1>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th scope="col">Reference</th><th scope="col">Flight</th><th scope="col">Route</th><th scope="col">Departure</th><th scope="col">Passengers</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Details</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td class="fw-semibold">{{ $booking->booking_reference }}</td>
                            <td>{{ $booking->flight->flight_number }}</td>
                            <td>{{ $booking->flight->departure_airport }} &rarr; {{ $booking->flight->arrival_airport }}</td>
                            <td>{{ $booking->flight->departure_date->format('M j, Y') }}<span class="d-block small text-muted-custom">{{ substr($booking->flight->departure_time, 0, 5) }}</span></td>
                            <td>{{ $booking->number_of_passengers }}</td>
                            <td>{{ $booking->booking_status }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('bookings.show', $booking) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="7">You do not have any bookings yet. <a href="{{ route('flights.index') }}">Search flights</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $bookings->links() }}</div>
    </section>
@endsection