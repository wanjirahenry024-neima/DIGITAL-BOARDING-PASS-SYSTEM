@extends('layouts.app')

@section('title', 'Add flight | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <p class="eyebrow mb-2"><a href="{{ route('admin.flights.index') }}">Flights</a> / Administration</p>
        <h1 class="h2 mb-4">Add flight</h1>
        <div class="content-panel p-4 p-md-5">
            @include('flights.admin._form', [
                'flight' => null,
                'action' => route('admin.flights.store'),
                'method' => 'POST',
                'submitLabel' => 'Create flight',
                'statuses' => $statuses,
            ])
        </div>
    </section>
@endsection