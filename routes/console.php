<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('exercises:create-daily')
    ->dailyAt('00:00')
    ->days([1, 2, 3, 4])
    ->withoutOverlapping()
    ->onOneServer();
