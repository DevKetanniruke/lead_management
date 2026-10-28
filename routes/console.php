<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leads:notify-overdue')->hourly()->withoutOverlapping();

// Invoice due-soon / overdue reminders to account managers (each sent once per invoice).
Schedule::command('invoices:notify-due')->dailyAt('08:00')->withoutOverlapping();
