<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('fuelline:compute-kpis')->dailyAt('02:00');

// Shopify catch-up sync. Webhooks handle order money/fulfilment in real time;
// this picks up what they cannot see — new B2B companies (a partner set up in
// Shopify has no order yet, so no webhook fires), address and name edits, and
// any webhook missed while the site was down. Cheap: one API call per 25
// companies. withoutOverlapping so a slow run never stacks.
Schedule::command('fuelline:shopify-import --exclude=Testing')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Newly imported accounts have a city but no coordinates until this runs.
Schedule::command('fuelline:geocode')
    ->dailyAt('02:30')
    ->withoutOverlapping();
