@extends('layouts.app')

@section('title', 'Create account | ' . config('app.name'))

@section('content')
    <section class="auth-background auth-background-register">
        <div class="container page-wrap auth-background-content">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="auth-panel p-4 p-md-5 fade-up">
                    <p class="eyebrow mb-2">Get ready to travel</p>
                    <h1 class="h2 mb-2">Create your account</h1>
                    <p class="text-muted-custom mb-4">A few details and your travel dashboard is ready.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">Please check the highlighted fields.</div>
                    @endif

                    <form method="POST" action="{{ route('register.store') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="full_name">Full name</label>
                            <input class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" autocomplete="name" required autofocus>
                            @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone">Phone number</label>
                            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirm password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <button class="btn btn-primary w-100 py-2" type="submit">Create account</button>
                    </form>

                    <p class="text-center small text-muted-custom mt-4 mb-0">
                        Already registered? <a href="{{ route('login') }}">Log in</a>
                    </p>
                </div>
            </div>
        </div>
        </div>
    </section>
@endsection