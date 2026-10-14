@extends('layouts.app')

@section('title', 'Choose a new password | ' . config('app.name'))

@section('content')
    <section class="container page-wrap py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="auth-panel p-4 p-md-5 fade-up">
                    <p class="eyebrow mb-2">Account recovery</p>
                    <h1 class="h2 mb-2">Choose a new password</h1>
                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                    @endif
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">New password</label>
                            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirm new password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <button class="btn btn-primary w-100 py-2" type="submit">Save new password</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection