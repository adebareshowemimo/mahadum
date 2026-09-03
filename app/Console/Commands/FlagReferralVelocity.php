<?php

namespace App\Console\Commands;

use App\Models\ReferralCode;
use App\Services\Settings;
use Illuminate\Console\Command;

/**
 * FR-7.5 velocity guard: any active referral code with more than the configured
 * number of sign-ups in the last 24h is flagged (frozen) for manual review.
 */
class FlagReferralVelocity extends Command
{
    protected $signature = 'referrals:flag-velocity';

    protected $description = 'Flag referral codes exceeding the 24h sign-up velocity limit';

    public function handle(Settings $settings): int
    {
        $limit = (int) $settings->get('referral.velocity_limit', 15);

        $flagged = ReferralCode::where('status', 'active')
            ->whereHas('referrals', fn ($q) => $q->where('signed_up_at', '>=', now()->subDay()), '>', $limit)
            ->update(['status' => 'flagged']);

        $this->info("Flagged {$flagged} referral code(s).");

        return self::SUCCESS;
    }
}
