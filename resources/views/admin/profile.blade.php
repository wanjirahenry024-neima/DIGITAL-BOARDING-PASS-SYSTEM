@extends('layouts.app')

@section('title', 'Administrator profile | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="mb-4">
            <p class="eyebrow mb-2">Administration</p>
            <h1 class="h2 mb-1">Profile</h1>
            <p class="text-muted-custom mb-0">Signed-in administrator account details.</p>
        </div>

        <div class="content-panel p-4 p-md-5">
            <p class="mono-label mb-2">Administrator account</p>
            <h2 class="h4 mb-1">{{ auth()->user()->full_name }}</h2>
            <p class="text-muted-custom mb-4">{{ auth()->user()->email }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-4">Role</dt>
                <dd class="col-sm-8">Administrator</dd>
                <dt class="col-sm-4">Account status</dt>
                <dd class="col-sm-8">{{ ucfirst(auth()->user()->status) }}</dd>
                <dt class="col-sm-4">Member since</dt>
                <dd class="col-sm-8">{{ auth()->user()->created_at->format('M j, Y') }}</dd>
            </dl>
        </div>
    </section>
@endsection
