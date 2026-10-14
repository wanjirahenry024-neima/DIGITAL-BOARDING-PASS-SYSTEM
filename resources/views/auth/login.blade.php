@extends('layouts.app')

@section('title', 'Log in | ' . config('app.name'))

@section('content')
    <section class="auth-background">
        <div class="container page-wrap auth-background-content">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="auth-panel p-4 p-md-5 fade-up">
                    <p class="eyebrow mb-2">Passenger access</p>
                    <h1 class="h2 mb-2">Welcome back</h1>
                    <p class="text-muted-custom mb-4">Log in to continue to your travel dashboard.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">Please check the highlighted fields.</div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="current-password" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button class="btn btn-primary w-100 py-2" type="submit">Log in</button>
                    </form>

                    <p class="text-center small text-muted-custom mt-4 mb-0">
                        <a href="{{ route('password.request') }}">Forgot your password?</a><br>
                        New here? <a href="{{ route('register') }}">Create an account</a>
                    </p>
                </div>
            </div>
        </div>
        </div>
    </section>
@endsection