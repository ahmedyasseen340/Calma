<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Every day: fetch new articles from the RSS feeds. The server must run `php artisan schedule:run` every minute (cron).
Schedule::command('articles:fetch')->daily();
