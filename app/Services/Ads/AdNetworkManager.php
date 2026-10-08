<?php

namespace App\Services\Ads;

/**
 * Resolves the outbound ad-network gateway. No real vendor is wired yet, so
 * even services.ads.live cannot enable inventory or rewards. The fallback
 * always returns unavailable/unverified until a concrete provider is added.
 */
class AdNetworkManager
{
    public function driver(): AdGateway
    {
        return new NullAdGateway;
    }
}
