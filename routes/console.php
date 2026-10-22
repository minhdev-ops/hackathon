<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR-GRANT-04: Redis drops K_g by TTL; this sweep flips the DB row to expired.
Schedule::command('grants:expire')->everyMinute()->withoutOverlapping();
