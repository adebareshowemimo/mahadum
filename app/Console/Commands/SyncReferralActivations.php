<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Services\Referral\ReferralService;
use Illuminate\Console\Command;

/**
 * Safety net for the activation gate (FR-7): re-checks every still-pending
 * referral in case a completion hook (lesson/quiz/subscription) didn't fire —
 * e.g. a subscription that renewed while the lessons/quizzes were done long ago.
 * `ReferralService::maybeActivate` is idempotent.
 */
class SyncReferralActivations extends Command
{
    protected $signature = 'referrals:sync-activations';

    protected $description = 'Activate any pending referral whose gate is now satisfied';

    public function handle(ReferralService $referrals): int
    {
        $activated = 0;

        Referral::where('status', 'pending')
            ->whereHas('referralCode', fn ($q) => $q->where('status', 'active'))
            ->with('referredUser')
            ->chunkById(200, function ($referrals_chunk) use ($referrals, &$activated) {
                foreach ($referrals_chunk as $referral) {
                    $referrals->maybeActivate($referral);
                    if ($referral->fresh()?->status === 'qualified') {
                        $activated++;
                    }
                }
            });

        $this->info("Activated {$activated} referral(s).");

        return self::SUCCESS;
    }
}
