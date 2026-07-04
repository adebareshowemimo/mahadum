<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Services\Settings;
use Illuminate\Console\Command;

/**
 * Deletes email-log rows older than the configured retention window
 * (email.log_retention_days; 0 disables). Keeps only what compliance needs and
 * caps how much recipient PII the log holds over time.
 */
class PruneEmailLog extends Command
{
    protected $signature = 'emails:prune-log';

    protected $description = 'Prune email-log rows past the retention window';

    public function handle(Settings $settings): int
    {
        $days = (int) $settings->get('email.log_retention_days', 365);

        if ($days <= 0) {
            $this->info('Email-log pruning is disabled (retention = 0).');

            return self::SUCCESS;
        }

        $deleted = EmailLog::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Pruned {$deleted} email-log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
