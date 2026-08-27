<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('exercises:create-daily')
    ->dailyAt('12:00')
    ->days([1, 2, 3, 4])
    ->timezone('Europe/Moscow')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('exercises:create-weekly')
    ->fridays()
    ->at('12:00')
    ->timezone('Europe/Moscow')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('exercises:send-reminders')
    ->dailyAt('18:00')
    ->timezone('Europe/Moscow')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('dictionary:enrich-transcriptions')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
