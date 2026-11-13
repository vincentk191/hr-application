<?php

use App\Support\Features;
use App\Support\Google\GoogleOAuth;
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
    ->timezone((string) config('attendance.device_timezone'))
    ->when(fn (): bool => Features::attendance());

// Seen notifications clear themselves a day after they were read. Hourly, so one is gone within an hour
// of its day being up. What was already announced is remembered separately, so nothing is sent twice.
Schedule::command('notifications:prune-read')->hourly();

// Switch off the accounts of people whose last working day has passed, the day after it, so they can
// work through it (OFF-CHK-010).
Schedule::command('offboarding:apply-exits')
    ->dailyAt('00:30')
    ->timezone((string) config('attendance.device_timezone'));

Schedule::command('notifications:document-expiry')
    ->dailyAt('08:00')
    ->timezone((string) config('attendance.device_timezone'));

// Interview reminders (REC-INT): candidates are emailed the day before, HR and the direct superior told the morning of.
// Each is sent once, so running it again changes nothing.
Schedule::command('recruitment:interview-reminders')
    ->dailyAt('08:00')
    ->timezone((string) config('attendance.device_timezone'));

// Interviews made through Google Calendar (REC-INT): follow a time changed there, flag an event deleted there. Outbound only, so
// nothing about Google is reachable from outside. Skipped when the app is not set up for Google at all.
Schedule::command('recruitment:sync-google-events')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->when(fn (): bool => app(GoogleOAuth::class)->isConfigured());
