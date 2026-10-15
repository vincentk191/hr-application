<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notifications that are not caused by a click. They need a scheduler running (the compose file has a
// `scheduler` service, or run `php artisan schedule:work`). Both are safe to run again: each person is
// told once.
Schedule::command('notifications:attendance-issues')
    ->weekdays()
    ->dailyAt('09:00')
    ->timezone((string) config('attendance.device_timezone'));

// Seen notifications clear themselves a day after they were read. Hourly, so one is gone within an hour
// of its day being up. What was already announced is remembered separately, so nothing is sent twice.
Schedule::command('notifications:prune-read')->hourly();

Schedule::command('notifications:document-expiry')
    ->dailyAt('08:00')
    ->timezone((string) config('attendance.device_timezone'));
