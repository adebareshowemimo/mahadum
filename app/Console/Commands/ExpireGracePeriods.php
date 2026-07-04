<?php

namespace App\Console\Commands;

use App\Models\TelcoSubscription;
use Illuminate\Console\Command;

/**
 * Moves telco subscriptions whose grace window has lapsed to soft_downgrade
 * (premium features off, account intact). A later successful charge reactivates.
 */
class ExpireGracePeriods extends Command
{
    protected $signature = 'telco:expire-grace';

    protected $description = 'Downgrade telco subscriptions whose grace period has expired';

    public function handle(): int
    {
        $count = TelcoSubscription::where('state', 'grace')
            ->whereNotNull('grace_until')
            ->where('grace_until', '<', now())
            ->update(['state' => 'soft_downgrade']);

        $this->info("Downgraded {$count} subscription(s).");

        return self::SUCCESS;
    }
}
