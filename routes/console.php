<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-reminders')->dailyAt('07:00');
Schedule::command('app:calculate-scores')->dailyAt('02:00');

// Récupération de l'heure configurable avec une valeur par défaut de 20:00
try {
    $reminderTime = \App\Models\Setting::where('key', 'report_reminder_time')->value('value') ?? '20:00';
} catch (\Throwable $e) {
    $reminderTime = '20:00';
}
Schedule::command('visits:remind-missing-reports')->dailyAt($reminderTime);
