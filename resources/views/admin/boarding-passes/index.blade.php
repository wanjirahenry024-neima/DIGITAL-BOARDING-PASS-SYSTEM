@extends('layouts.app')

@section('title', 'Boarding passes | ' . config('app.name'))

@section('content')
    <section class="container-fluid px-3 px-lg-4 py-4">
        <div class="mb-4">
            <p class="eyebrow mb-2">Administration</p>
            <h1 class="h2 mb-1">Boarding passes</h1>
            <p class="text-muted-custom mb-0">Review issued passes without exposing QR verification credentials.</p>
        </div>

        <form class="content-panel p-3 mb-4" method="GET" action="{{ route('admin.boarding-passes.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label" for="q">Search passes</label>
                    <input class="form-control" id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Pass, booking, passenger, or flight">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="pass_status">Status</label>
                    <select class="form-select" id="pass_status" name="pass_status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('pass_status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.boarding-passes.index') }}">Clear</a>
                </div>
            </div>
        </form>

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Pass reference</th><th>Passenger</th><th>Flight</th><th>Seat</th><th>Issued</th><th>Expires</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($boardingPasses as $pass)
                        <tr>
                            <td class="font-monospace fw-semibold">{{ $pass->pass_reference }}</td>
                            <td>{{ $pass->passenger->full_name }}</td>
                            <td>{{ $pass->flight->flight_number }}</td>
                            <td>{{ $pass->seat_number }}</td>
                            <td class="text-nowrap">{{ $pass->issued_at->format('M j, Y H:i') }}</td>
                            <td class="text-nowrap">{{ $pass->expires_at->format('M j, Y H:i') }}</td>
                            <td><span class="badge text-bg-{{ $pass->pass_status === \App\Models\BoardingPass::STATUS_ACTIVE ? 'success' : 'secondary' }}">{{ $pass->pass_status }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="7">No boarding passes match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $boardingPasses->links() }}</div>
    </section>
@endsection
