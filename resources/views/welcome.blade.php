@extends('layouts.app')

@section('title', 'Welcome | ' . config('app.name'))

@section('content')
    <section class="landing-hero">
        <div class="container page-wrap landing-hero-content">
            <div class="row align-items-center g-5 py-lg-5">
                <div class="col-lg-7 fade-up">
                    <p class="eyebrow mb-4">Regional journeys, one clear departure point</p>
                    <h1 class="hero-title mb-4">Your next flight starts here.</h1>
                    <p class="lead text-muted-custom mb-4" style="max-width: 550px">
                        Manage your travel in one place, from booking through boarding.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-primary btn-lg px-4" href="{{ route('register') }}">Create your account</a>
                        <a class="btn btn-outline-secondary btn-lg px-4" href="{{ route('login') }}">Log in</a>
                    </div>
                </div>
                <div class="col-lg-5 fade-up-delay">
                    <div class="hero-panel p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <span class="mono-label">A simpler departure</span>
                            <span class="status-dot" aria-label="Ready"></span>
                        </div>
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div>
                                <span class="mono-label d-block mb-2">From</span>
                                <strong class="display-6">Your town</strong>
                            </div>
                            <span class="flex-grow-1 route-line"></span>
                            <div class="text-end">
                                <span class="mono-label d-block mb-2">To</span>
                                <strong class="display-6">Anywhere</strong>
                            </div>
                        </div>
                        <p class="mb-0 text-white-50">A digital-first travel experience for regional airports.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection