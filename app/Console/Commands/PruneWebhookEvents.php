<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

/**
 * Keeps the webhook_events idempotency ledger bounded. Only *processed* events
 * (those with a processed_at) past the retention window are dropped — unprocessed
 * `received` rows are left in place since they flag a mid-processing failure worth
 * investigating. The retention window is comfortably longer than any gateway's
 * replay/retry horizon, so pruning never reopens a duplicate.
 */
class PruneWebhookEvents extends Command
{
    protected $signature = 'webhooks:prune {--days=90 : Retention window in days}';

    protected $description = 'Delete processed webhook events past the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = WebhookEvent::whereNotNull('processed_at')
            ->where('processed_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} webhook event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
