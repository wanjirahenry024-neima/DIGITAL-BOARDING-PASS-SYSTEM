@extends('layouts.app')

@section('title', 'Users | ' . config('app.name'))

@section('content')
    <section class="container-fluid px-3 px-lg-4 py-4">
        <div class="mb-4">
            <p class="eyebrow mb-2">Administration</p>
            <h1 class="h2 mb-1">Users</h1>
            <p class="text-muted-custom mb-0">Search accounts and manage their role and access status.</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->has('user'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('user') }}</div>
        @endif

        <form class="content-panel p-3 mb-4" method="GET" action="{{ route('admin.users.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="q">Search users</label>
                    <input class="form-control" id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Name or email">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst($role) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Apply filters</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Clear</a>
                </div>
            </div>
        </form>

        <div class="content-panel table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>User</th><th>Phone</th><th>Joined</th><th>Role and access</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $user->full_name }}</span>
                                <span class="d-block small text-muted-custom">{{ $user->email }}</span>
                            </td>
                            <td>{{ $user->phone ?? '—' }}</td>
                            <td class="text-nowrap">{{ $user->created_at->format('M j, Y') }}</td>
                            <td>
                                <form class="d-flex flex-wrap gap-2 align-items-center" method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="visually-hidden" for="role-{{ $user->id }}">Role for {{ $user->full_name }}</label>
                                    <select class="form-select form-select-sm w-auto" id="role-{{ $user->id }}" name="role">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                                        @endforeach
                                    </select>
                                    <label class="visually-hidden" for="status-{{ $user->id }}">Status for {{ $user->full_name }}</label>
                                    <select class="form-select form-select-sm w-auto" id="status-{{ $user->id }}" name="status">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected($user->status === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted-custom py-5" colspan="4">No users match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </section>
@endsection
