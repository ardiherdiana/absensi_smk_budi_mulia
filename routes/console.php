<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mirrors backend/src/lib/reminderScheduler.ts's 60s setInterval - needs
// either `php artisan schedule:work` running in dev or a real cron entry
// (* * * * * php artisan schedule:run) in production.
Schedule::command('attendance:send-reminders')->everyMinute();
