@extends('layouts.app')

@section('title', 'Forgot password | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="auth-panel p-4 p-md-5 fade-up">
                    <p class="eyebrow mb-2">Account recovery</p>
                    <h1 class="h2 mb-2">Reset your password</h1>
                    <p class="text-muted-custom mb-4">Enter your account email and we’ll send a password reset link.</p>
                    @if (session('status'))
                        <div class="alert alert-success" role="status">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                    @endif
                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <button class="btn btn-primary w-100 py-2" type="submit">Email password reset link</button>
                    </form>
                    <p class="text-center small mt-4 mb-0"><a href="{{ route('login') }}">Back to log in</a></p>
                </div>
            </div>
        </div>
    </section>
@endsection