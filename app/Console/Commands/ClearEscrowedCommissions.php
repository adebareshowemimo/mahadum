<?php

namespace App\Console\Commands;

use App\Models\Commission;
use Illuminate\Console\Command;

/**
 * Clears commissions whose 14-day escrow has elapsed (Rule 9 / FR-7.3), as long
 * as the underlying referral wasn't rejected (a chargeback in-window would have
 * cancelled it). Cleared commissions become payable.
 */
class ClearEscrowedCommissions extends Command
{
    protected $signature = 'commissions:clear-escrow';

    protected $description = 'Clear commissions past their escrow window';

    public function handle(): int
    {
        $cleared = 0;

        Commission::where('status', 'pending_escrow')
            ->where('escrow_until', '<', now())
            ->whereHas('referral', fn ($q) => $q->where('status', '!=', 'rejected'))
            ->chunkById(200, function ($commissions) use (&$cleared) {
                foreach ($commissions as $commission) {
                    $commission->update(['status' => 'cleared', 'cleared_at' => now()]);
                    $cleared++;
                }
            });

        $this->info("Cleared {$cleared} commission(s).");

        return self::SUCCESS;
    }
}
