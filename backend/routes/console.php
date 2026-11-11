<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('council:run-scheduled audit-verify')->dailyAt('02:00')->timezone('Africa/Harare')->withoutOverlapping();
Schedule::command('council:run-scheduled licence-maintenance')->dailyAt('03:00')->timezone('Africa/Harare')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
