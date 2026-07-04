<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Telco airtime billing lifecycle (Backend Architecture §6.5).
Schedule::command('telco:bill-daily')->dailyAt('02:00');
Schedule::command('telco:expire-grace')->hourly();

// Referrals & commissions.
Schedule::command('commissions:clear-escrow')->hourly();
Schedule::command('referrals:flag-velocity')->everyFifteenMinutes();

// Upcoming-renewal reminders for card/invoice subscriptions (telco auto-bills).
Schedule::command('subscriptions:remind')->dailyAt('09:00');

// Housekeeping: keep the webhook idempotency ledger bounded.
Schedule::command('webhooks:prune')->dailyAt('03:30');
