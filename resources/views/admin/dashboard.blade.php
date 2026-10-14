@extends('layouts.app')

@section('title', 'Administration | ' . config('app.name'))

@section('content')
    @php
        $cards = [
            ['label' => 'Passengers', 'key' => 'passengers', 'route' => 'admin.users.index', 'query' => ['role' => 'passenger'], 'color' => 'primary'],
            ['label' => 'Airport staff', 'key' => 'staff', 'route' => 'admin.users.index', 'query' => ['role' => 'staff'], 'color' => 'success'],
            ['label' => 'Flights', 'key' => 'flights', 'route' => 'admin.flights.index', 'query' => [], 'color' => 'info'],
            ['label' => 'Bookings', 'key' => 'bookings', 'route' => 'admin.bookings.index', 'query' => [], 'color' => 'warning'],
            ['label' => 'Check-ins', 'key' => 'check_ins', 'route' => 'admin.bookings.index', 'query' => [], 'color' => 'secondary'],
            ['label' => 'Active passes', 'key' => 'active_boarding_passes', 'route' => 'admin.boarding-passes.index', 'query' => ['pass_status' => 'ACTIVE'], 'color' => 'success'],
            ['label' => 'Used passes', 'key' => 'used_boarding_passes', 'route' => 'admin.boarding-passes.index', 'query' => ['pass_status' => 'USED'], 'color' => 'secondary'],
            ['label' => 'Invalid attempts', 'key' => 'invalid_verifications', 'route' => 'admin.verification-records.index', 'query' => ['result' => 'INVALID'], 'color' => 'danger'],
            ['label' => "Today's boarding", 'key' => 'today_boarding_activity', 'route' => 'admin.reports.index', 'query' => [], 'color' => 'primary'],
            ['label' => 'Offline verifications', 'key' => 'offline_verifications', 'route' => 'admin.verification-records.index', 'query' => ['verification_method' => 'OFFLINE'], 'color' => 'info'],
            ['label' => 'Pending synchronization', 'key' => 'pending_synchronization', 'route' => 'admin.verification-records.index', 'query' => [], 'color' => 'warning'],
        ];
    @endphp

    <section class="container-fluid admin-dashboard px-3 px-lg-4 py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <p class="eyebrow mb-2">Airport operations · Administration</p>
                <h1 class="h2 mb-1">Operations dashboard</h1>
                <p class="text-muted-custom mb-0">Live overview of passengers, flights, boarding and system synchronization.</p>
            </div>
            <div class="content-panel px-3 py-2">
                <span class="mono-label d-block">Administrator</span>
                <span class="fw-semibold">{{ auth()->user()->full_name }}</span>
            </div>
        </div>
        <p class="small text-muted-custom mb-3">
            Offline verification and synchronization metrics reflect records received centrally; scans still queued on a disconnected gate terminal appear after it reconnects.
        </p>

        <div class="row g-3 mb-4">
            @foreach ($cards as $card)
                <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                    <a class="content-panel metric-card d-block h-100 p-3 text-decoration-none" href="{{ route($card['route'], $card['query']) }}">
                        <span class="d-flex align-items-center gap-2 small text-muted-custom">
                            <span class="badge text-bg-{{ $card['color'] }}">&nbsp;</span>
                            {{ $card['label'] }}
                        </span>
                        <span class="d-block display-6 fw-semibold mt-2 text-body">{{ number_format($statistics[$card['key']]) }}</span>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="content-panel overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center gap-3 p-3 border-bottom">
                        <div>
                            <p class="mono-label mb-1">Gate activity</p>
                            <h2 class="h5 mb-0">Recent verifications</h2>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.verification-records.index') }}">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr><th>Time</th><th>Pass reference</th><th>Flight</th><th>Staff</th><th>Result</th><th>Method</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($recentVerifications as $verification)
                                    <tr>
                                        <td class="text-nowrap">{{ $verification->verified_at->format('M j, H:i') }}</td>
                                        <td class="font-monospace">{{ $verification->verification_reference ?? '—' }}</td>
                                        <td>{{ $verification->flight?->flight_number ?? '—' }}</td>
                                        <td>{{ $verification->staff?->full_name ?? '—' }}</td>
                                        <td><span class="badge text-bg-{{ $verification->result === 'VALID' ? 'success' : 'secondary' }}">{{ $verification->result }}</span></td>
                                        <td>{{ $verification->verification_method }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted-custom py-4" colspan="6">No verification activity recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-xl-4">
                <section class="content-panel p-3 h-100">
                    <p class="mono-label mb-1">Quick access</p>
                    <h2 class="h5 mb-3">Administration</h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary text-start" href="{{ route('admin.users.index') }}">Manage users <span class="float-end" aria-hidden="true">&rarr;</span></a>
                        <a class="btn btn-outline-primary text-start" href="{{ route('admin.flights.index') }}">Manage flights <span class="float-end" aria-hidden="true">&rarr;</span></a>
                        <a class="btn btn-outline-primary text-start" href="{{ route('admin.bookings.index') }}">Manage bookings <span class="float-end" aria-hidden="true">&rarr;</span></a>
                        <a class="btn btn-outline-primary text-start" href="{{ route('admin.audit-logs.index') }}">Review audit logs <span class="float-end" aria-hidden="true">&rarr;</span></a>
                    </div>
                    <p class="small text-muted-custom mt-3 mb-0">Offline scan records appear here after a gate terminal synchronizes with the central server.</p>
                </section>
            </div>
        </div>
    </section>
@endsection