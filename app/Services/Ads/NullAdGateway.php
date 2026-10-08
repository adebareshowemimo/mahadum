<?php

namespace App\Services\Ads;

/**
 * No-op ad network used when no live vendor is configured (local/CI, and
 * production until a vendor is chosen — see AdNetworkManager). No inventory
 * or verified reward exists without a provider. Tests inject a verified gateway
 * explicitly rather than granting rewards through this fallback.
 */
class NullAdGateway implements AdGateway
{
    public function available(string $placement): bool
    {
        return false;
    }

    public function verifyReward(string $adRef): bool
    {
        return false;
    }
}
