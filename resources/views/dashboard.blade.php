@extends('layouts.app')

@section('title', 'Dashboard | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="fade-up mb-5">
            <p class="eyebrow mb-2">Passenger dashboard</p>
            <h1 class="h2 mb-2">Good to have you here, {{ auth()->user()->full_name }}.</h1>
            <p class="text-muted-custom mb-0">Your travel details will appear here as you plan your next journey.</p>
        </div>

        <a class="btn btn-primary mb-4" href="{{ route('flights.index') }}">Search flights</a>
        <a class="btn btn-outline-primary mb-4 ms-2" href="{{ route('bookings.index') }}">My bookings</a>
        <a class="btn btn-outline-secondary mb-4 ms-2" href="{{ route('notifications.index') }}">Notification history</a>

        <div class="content-panel p-4 p-md-5 fade-up-delay">
            <div class="row align-items-center g-4">
                <div class="col-md-8">
                    <p class="mono-label mb-2">No trips on the board</p>
                    <h2 class="h4 mb-2">Ready when you are.</h2>
                    <p class="text-muted-custom mb-0">There are no bookings associated with your account yet.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge text-bg-light border px-3 py-2">{{ ucfirst(auth()->user()->role) }} account</span>
                </div>
            </div>
        </div>
    </section>
@endsection