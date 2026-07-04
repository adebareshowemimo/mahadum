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

// Teacher class compensation — accrue for the prior month on the 1st.
Schedule::command('compensation:accrue-teachers')->monthlyOn(1, '03:00');

// Upcoming-renewal reminders for card/invoice subscriptions (telco auto-bills).
Schedule::command('subscriptions:remind')->dailyAt('09:00');

// Housekeeping: keep the webhook idempotency ledger bounded.
Schedule::command('webhooks:prune')->dailyAt('03:30');

// Send scheduled email campaigns whose time has arrived.
Schedule::command('emails:dispatch-scheduled')->everyFiveMinutes();

// Prune email-log rows past the retention window.
Schedule::command('emails:prune-log')->dailyAt('04:00');
