@extends('layouts.app')

@section('title', 'Manage bookings | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="mb-4">
            <p class="eyebrow mb-2">Administration</p>
            <h1 class="h2 mb-0">Bookings</h1>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form class="content-panel p-3 mb-4" method="GET" action="{{ route('admin.bookings.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label" for="q">Search bookings</label>
                    <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Reference, passenger, or flight number">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="booking_status">Status</label>
                    <select class="form-select" id="booking_status" name="booking_status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('booking_status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.bookings.index') }}" aria-label="Clear filters">Clear</a>
                </div>
            </div>
        </form>

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th scope="col">Reference</th><th scope="col">Passenger</th><th scope="col">Flight</th><th scope="col">Booking date</th><th scope="col">Passengers</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Details</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td class="fw-semibold">{{ $booking->booking_reference }}</td>
                            <td>{{ $booking->passenger->full_name }}<span class="d-block small text-muted-custom">{{ $booking->passenger->email }}</span></td>
                            <td>{{ $booking->flight->flight_number }}</td>
                            <td>{{ $booking->booking_date->format('M j, Y') }}</td>
                            <td>{{ $booking->number_of_passengers }}</td>
                            <td>{{ $booking->booking_status }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.bookings.show', $booking) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="7">No bookings match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $bookings->links() }}</div>
    </section>
@endsection