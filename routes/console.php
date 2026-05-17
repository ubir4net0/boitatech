<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ingest:fire-alerts --limit=5000 --days=2')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ingest:fire-risk --days=3 --max-features=5000')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ingest:deter --days=30 --max-features=1500')
    ->everyTwoHours()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('build:priority-zones --days=30')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ingest:deter-backfill --start-date=' . now()->subDays(60)->toDateString() . ' --end-date=' . now()->subDays(16)->toDateString() . ' --chunk-days=10 --page-size=1500 --max-pages=60 --max-features=120000')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('lgpd:purge-data')
    ->dailyAt('02:40')
    ->withoutOverlapping()
    ->onOneServer();
