@extends('layouts.app')

@section('title', 'Edit ' . $flight->flight_number . ' | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2"><a href="{{ route('admin.flights.index') }}">Flights</a> / {{ $flight->flight_number }}</p>
        <h1 class="h2 mb-4">Edit flight</h1>
        <div class="content-panel p-4 p-md-5">
            @include('flights.admin._form', [
                'flight' => $flight,
                'action' => route('admin.flights.update', $flight),
                'method' => 'PUT',
                'submitLabel' => 'Save changes',
                'statuses' => $statuses,
            ])
        </div>
    </section>
@endsection