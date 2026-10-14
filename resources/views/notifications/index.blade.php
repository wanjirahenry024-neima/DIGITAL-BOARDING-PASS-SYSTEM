@extends('layouts.app')

@section('title', 'Notifications | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <p class="eyebrow mb-2">Passenger account</p>
                <h1 class="h2 mb-2">Notification history</h1>
                <p class="text-muted-custom mb-0">Travel updates and account notices.</p>
            </div>
            <span class="badge text-bg-light border px-3 py-2">{{ $unreadCount }} unread</span>
        </div>

        <div class="d-grid gap-3">
            @forelse ($notifications as $notification)
                <article class="content-panel p-4 {{ $notification->read_at ? '' : 'border-success' }}">
                    <div class="d-flex flex-wrap justify-content-between gap-3">
                        <div>
                            <p class="mono-label mb-2">{{ str_replace('_', ' ', $notification->data['event'] ?? 'travel update') }}</p>
                            <h2 class="h5 mb-2">{{ $notification->data['title'] ?? 'Travel update' }}</h2>
                            <p class="text-muted-custom mb-2">{{ $notification->data['message'] ?? '' }}</p>
                            <a href="{{ $notification->data['url'] ?? route('dashboard') }}">View details</a>
                            <p class="small text-muted-custom mt-3 mb-0">
                                {{ $notification->created_at->format('M j, Y · H:i') }}
                                @if (! $notification->read_at)
                                    <span class="badge text-bg-success ms-2">New</span>
                                @endif
                            </p>
                        </div>
                        @if (! $notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification->getKey()) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Mark as read</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="content-panel p-5 text-center">
                    <h2 class="h5">You’re all caught up</h2>
                    <p class="text-muted-custom mb-0">There are no notifications in your history yet.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $notifications->links() }}</div>
    </section>
@endsection
