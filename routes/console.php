<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diario de la BD a SharePoint (docs/19). En cPanel sin cron-cada-minuto
// se puede llamar `backup:crear` directo desde un Cron Job diario.
Schedule::command('backup:crear')
    ->dailyAt('02:00')
    ->withoutOverlapping();
