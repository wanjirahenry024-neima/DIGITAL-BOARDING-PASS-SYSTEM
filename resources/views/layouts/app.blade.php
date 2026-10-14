<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="site-shell d-flex flex-column">
        <nav class="site-nav navbar navbar-expand-md py-3">
            <div class="container page-wrap">
                <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="{{ route('home') }}">
                    <span class="brand-mark" aria-hidden="true">D</span>
                    <span class="brand-name">{{ config('app.name') }}</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-navigation" aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="main-navigation">
                    <div class="navbar-nav ms-auto align-items-md-center gap-md-2 pt-3 pt-md-0">
                        @auth
                            @if (auth()->user()->role === 'passenger')
                                <a @class(['nav-link', 'active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}">Dashboard</a>
                                <a @class(['nav-link', 'active' => request()->routeIs('bookings.*')]) href="{{ route('bookings.index') }}">My bookings</a>
                                <a @class(['nav-link', 'active' => request()->routeIs('notifications.*')]) href="{{ route('notifications.index') }}">Notifications</a>
                            @elseif (auth()->user()->role === 'staff')
                                <a @class(['nav-link', 'active' => request()->routeIs('staff.*')]) href="{{ route('staff.dashboard') }}">Staff workspace</a>
                            @elseif (auth()->user()->role === 'admin')
                                <div class="nav-item dropdown">
                                    <a @class(['nav-link', 'dropdown-toggle', 'active' => request()->routeIs('admin.*')]) href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Administration
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">Users</a></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.flights.*')]) href="{{ route('admin.flights.index') }}">Flights</a></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.bookings.*')]) href="{{ route('admin.bookings.index') }}">Bookings</a></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.boarding-passes.*')]) href="{{ route('admin.boarding-passes.index') }}">Boarding passes</a></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.verification-records.*')]) href="{{ route('admin.verification-records.index') }}">Verification records</a></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.audit-logs.*')]) href="{{ route('admin.audit-logs.index') }}">Audit logs</a></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.reports.*')]) href="{{ route('admin.reports.index') }}">System reports</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a @class(['dropdown-item', 'active' => request()->routeIs('admin.profile')]) href="{{ route('admin.profile') }}">Profile</a></li>
                                    </ul>
                                </div>
                            @else
                                <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                            @endif
                            <span class="nav-link user-name text-muted-custom">{{ auth()->user()->full_name }}</span>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Logout</button>
                            </form>
                        @else
                            <a class="nav-link" href="{{ route('login') }}">Log in</a>
                            <a class="btn btn-sm btn-primary px-3" href="{{ route('register') }}">Create account</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-grow-1">
            @if (session('status'))
                <div class="container page-wrap pt-4">
                    <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
                </div>
            @endif
            @yield('content')
        </main>

        <footer class="container page-wrap py-4 mt-5 border-top" style="border-color: var(--line) !important">
            <div class="d-flex flex-wrap justify-content-between gap-2 small text-muted-custom">
                <span>{{ config('app.name') }}</span>
                <span>Regional travel, ready for the gate.</span>
            </div>
        </footer>
    </div>
</body>
</html>