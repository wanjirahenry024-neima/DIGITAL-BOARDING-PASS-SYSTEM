@extends('layouts.app')

@section('title', 'Verification records | ' . config('app.name'))

@section('content')
    <section class="container-fluid px-3 px-lg-4 py-4">
        <div class="mb-4">
            <p class="eyebrow mb-2">Administration</p>
            <h1 class="h2 mb-1">Verification records</h1>
            <p class="text-muted-custom mb-0">Online and synchronized offline scans, including failed attempts and conflicts.</p>
        </div>

        <form class="content-panel p-3 mb-4" method="GET" action="{{ route('admin.verification-records.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Reference, person, flight, gate">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="result">Result</label>
                    <select class="form-select" id="result" name="result">
                        <option value="">All results</option>
                        @foreach ($results as $result)
                            <option value="{{ $result }}" @selected(request('result') === $result)>{{ $result }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label" for="verification_method">Method</label>
                    <select class="form-select" id="verification_method" name="verification_method">
                        <option value="">All methods</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" @selected(request('verification_method') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label" for="sync_status">Sync status</label>
                    <select class="form-select" id="sync_status" name="sync_status">
                        <option value="">All states</option>
                        @foreach ($syncStatuses as $syncStatus)
                            <option value="{{ $syncStatus }}" @selected(request('sync_status') === $syncStatus)>{{ $syncStatus }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label" for="verified_date">Date</label>
                    <input class="form-control" id="verified_date" name="verified_date" type="date" value="{{ request('verified_date') }}">
                </div>
                <div class="col-lg-1 d-grid">
                    <button class="btn btn-primary" type="submit">Filter</button>
                </div>
            </div>
            <a class="small d-inline-block mt-2" href="{{ route('admin.verification-records.index') }}">Clear all filters</a>
        </form>

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Time</th><th>Reference</th><th>Passenger</th><th>Flight</th><th>Staff</th><th>Gate</th><th>Result</th><th>Method</th><th>Sync</th></tr></thead>
                <tbody>
                    @forelse ($verificationRecords as $record)
                        <tr>
                            <td class="text-nowrap">{{ $record->verified_at->format('M j, Y H:i:s') }}</td>
                            <td class="font-monospace">{{ $record->verification_reference ?? '—' }}</td>
                            <td>{{ $record->passenger?->full_name ?? '—' }}</td>
                            <td>{{ $record->flight?->flight_number ?? '—' }}</td>
                            <td>{{ $record->staff?->full_name ?? '—' }}</td>
                            <td>{{ $record->gate ?? '—' }}</td>
                            <td><span class="badge text-bg-{{ $record->result === 'VALID' ? 'success' : 'secondary' }}">{{ $record->result }}</span></td>
                            <td>{{ $record->verification_method }}</td>
                            <td>{{ $record->sync_status }}</td>
                        </tr>
                        @if ($record->notes)
                            <tr><td class="small text-muted-custom pt-0 border-0" colspan="9">{{ $record->notes }}</td></tr>
                        @endif
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="9">No verification records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $verificationRecords->links() }}</div>
    </section>
@endsection
