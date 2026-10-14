@extends('layouts.app')

@section('title', $reportTitle . ' | ' . config('app.name'))

@section('content')
    <style>
        @media print {
            .site-nav, footer, .report-controls, .pagination, .no-print { display: none !important; }
            body { background: #fff !important; }
            .content-panel { border: 0 !important; box-shadow: none !important; }
            .report-table { font-size: 10pt; }
        }
    </style>

    <section class="container-fluid admin-report px-3 px-lg-4 py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <p class="eyebrow mb-2">Administration · Operational intelligence</p>
                <h1 class="h2 mb-1">System reports</h1>
                <p class="text-muted-custom mb-0">Airport activity, gate performance and synchronized offline operations.</p>
            </div>
            <div class="d-flex gap-2 report-controls">
                <a class="btn btn-outline-primary" href="{{ route('admin.reports.export', request()->query()) }}">Export CSV</a>
                <button class="btn btn-primary" type="button" onclick="window.print()">Print report</button>
            </div>
        </div>

        <form class="content-panel p-3 mb-4 report-controls" method="GET" action="{{ route('admin.reports.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label" for="report">Report</label>
                    <select class="form-select" id="report" name="report">
                        @foreach ($reports as $key => $label)
                            <option value="{{ $key }}" @selected($selectedReport === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label" for="start_date">From date</label>
                    <input class="form-control" id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label" for="end_date">To date</label>
                    <input class="form-control" id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-xl-2 col-md-4">
                    <label class="form-label" for="flight_id">Flight</label>
                    <select class="form-select" id="flight_id" name="flight_id">
                        <option value="">All flights</option>
                        @foreach ($flights as $flight)
                            <option value="{{ $flight->id }}" @selected((string) ($filters['flight_id'] ?? '') === (string) $flight->id)>
                                {{ $flight->flight_number }} · {{ $flight->departure_date->format('M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label class="form-label" for="airport">Airport</label>
                    <select class="form-select" id="airport" name="airport">
                        <option value="">All airports</option>
                        @foreach ($airports as $code => $name)
                            <option value="{{ $code }}" @selected(($filters['airport'] ?? '') === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label class="form-label" for="verification_result">Verification result</label>
                    <select class="form-select" id="verification_result" name="verification_result">
                        <option value="">All results</option>
                        @foreach ($results as $result)
                            <option value="{{ $result }}" @selected(($filters['verification_result'] ?? '') === $result)>{{ $result }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-4">
                    <label class="form-label" for="staff_id">Staff member</label>
                    <select class="form-select" id="staff_id" name="staff_id">
                        <option value="">All staff members</option>
                        @foreach ($staffMembers as $staffMember)
                            <option value="{{ $staffMember->id }}" @selected((string) ($filters['staff_id'] ?? '') === (string) $staffMember->id)>{{ $staffMember->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label class="form-label" for="verification_method">Verification method</label>
                    <select class="form-select" id="verification_method" name="verification_method">
                        <option value="">Online and offline</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" @selected(($filters['verification_method'] ?? '') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-4 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Run report</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.reports.index', ['report' => $selectedReport]) }}">Clear</a>
                </div>
            </div>
        </form>

        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div>
                <p class="mono-label mb-1">Selected report</p>
                <h2 class="h4 mb-0">{{ $reportTitle }}</h2>
            </div>
            <div class="content-panel px-3 py-2">
                <span class="small text-muted-custom">Matching records</span>
                <strong class="fs-4 ms-2">{{ number_format($summary['total']) }}</strong>
            </div>
        </div>

        @if ($summary['breakdown']->isNotEmpty())
            <div class="row g-3 mb-4">
                @foreach ($summary['breakdown'] as $item)
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="content-panel p-3 h-100">
                            <span class="mono-label d-block">{{ $item->outcome ?? 'Unspecified' }}</span>
                            <strong class="fs-3">{{ number_format($item->total) }}</strong>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($selectedReport === 'synchronization')
            <section class="content-panel p-3 mb-4">
                <h3 class="h6 mb-3">Local terminal outbox</h3>
                @if ($summary['local_sync'] === [])
                    <p class="text-muted-custom mb-0">No local terminal outbox is available on this server. Synchronized central records remain listed below.</p>
                @else
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($summary['local_sync'] as $state => $count)
                            <div><span class="badge text-bg-secondary">{{ $state }}</span> <strong>{{ number_format($count) }}</strong></div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <div class="content-panel table-responsive">
            <table class="table table-hover align-middle mb-0 report-table">
                <thead>
                    <tr>
                        <th>Date and time</th>
                        <th>Reference</th>
                        <th>Passenger / staff</th>
                        <th>Flight / route</th>
                        <th>Airport</th>
                        <th>Result / status</th>
                        <th>Method / sync</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td class="text-nowrap">{{ $record->report_date ? \Illuminate\Support\Carbon::parse($record->report_date)->format('M j, Y H:i') : '—' }}</td>
                            <td class="font-monospace">{{ $record->reference ?? '—' }}</td>
                            <td>
                                {{ $record->passenger_name ?? $record->staff_name ?? '—' }}
                                @if ($record->passenger_name && $record->staff_name)
                                    <span class="d-block small text-muted-custom">Staff: {{ $record->staff_name }}</span>
                                @endif
                            </td>
                            <td>{{ $record->flight_number ?? '—' }}</td>
                            <td>
                                @if ($record->departure_airport || $record->arrival_airport)
                                    {{ $record->departure_airport ?? '—' }} <span aria-hidden="true">&rarr;</span> {{ $record->arrival_airport ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $record->outcome ?? '—' }}</td>
                            <td>
                                {{ $record->method ?? '—' }}
                                @if ($record->sync_status)
                                    <span class="d-block small text-muted-custom">{{ $record->sync_status }}</span>
                                @endif
                            </td>
                            <td>{{ $record->details ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="8">No records match the selected report and filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3 report-controls">{{ $records->links() }}</div>
    </section>
@endsection
