<?php

namespace App\Services\Ads;

/**
 * No-op ad network used when no live vendor is configured (local/CI, and
 * production until a vendor is chosen — see AdNetworkManager). An ad is
 * always "available" and always "verified", so the reward flow can be
 * exercised end-to-end without a real network.
 */
class NullAdGateway implements AdGateway
{
    public function available(string $placement): bool
    {
        return true;
    }

    public function verifyReward(string $adRef): bool
    {
        return true;
    }
}
