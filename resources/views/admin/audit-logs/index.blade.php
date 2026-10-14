@extends('layouts.app')

@section('title', 'Audit logs | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="mb-4">
            <p class="eyebrow mb-2">Administration</p>
            <h1 class="h2 mb-0">Audit logs</h1>
            <p class="text-muted-custom mt-2 mb-0">Append-only records of authentication, booking, check-in, boarding, verification, and administrator actions.</p>
        </div>

        <form class="content-panel p-3 mb-4" method="GET" action="{{ route('admin.audit-logs.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="q">Search audit trail</label>
                    <input class="form-control" id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Event, actor, subject ID, or details">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="event">Event</label>
                    <select class="form-select" id="event" name="event">
                        <option value="">All events</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected(request('event') === $event)>{{ str_replace('_', ' ', ucfirst($event)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="occurred_date">Date</label>
                    <input class="form-control" id="occurred_date" name="occurred_date" type="date" value="{{ request('occurred_date') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.audit-logs.index') }}">Clear</a>
                </div>
            </div>
        </form>

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Event</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Details</th>
                        <th scope="col">IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditLogs as $auditLog)
                        <tr>
                            <td class="text-nowrap">{{ $auditLog->occurred_at->format('M j, Y H:i:s') }}</td>
                            <td class="fw-semibold">{{ str_replace('_', ' ', $auditLog->event) }}</td>
                            <td>
                                {{ $auditLog->actor?->full_name ?? ($auditLog->event === \App\Models\AuditLog::EVENT_LOGIN_FAILED ? 'Unauthenticated' : 'System') }}
                                @if ($auditLog->actor)
                                    <span class="d-block small text-muted-custom">{{ $auditLog->actor->email }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($auditLog->subject_type)
                                    {{ class_basename($auditLog->subject_type) }} #{{ $auditLog->subject_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($auditLog->description)
                                    <span class="d-block">{{ $auditLog->description }}</span>
                                @endif
                                @if ($auditLog->properties)
                                    <pre class="small mb-0 text-wrap">{{ json_encode($auditLog->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                @if ($auditLog->user_agent)
                                    <span class="d-block small text-muted-custom">{{ $auditLog->user_agent }}</span>
                                @endif
                            </td>
                            <td>{{ $auditLog->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="6">No audit events have been recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $auditLogs->links() }}</div>
    </section>
@endsection
