<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gate:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('services.gate_terminal.enabled'));
