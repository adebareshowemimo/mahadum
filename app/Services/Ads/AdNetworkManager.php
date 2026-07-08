<?php

namespace App\Services\Ads;

/**
 * Resolves the outbound ad-network gateway. Returns a NullAdGateway (always
 * available, always verified) unless `services.ads.live` is on — mirrors
 * PaymentGatewayManager/TelcoGatewayManager. No real vendor (AdMob, Unity,
 * etc.) is wired yet; `services.ads.live` has nothing to turn on to until one
 * is chosen and a concrete AdGateway added here.
 */
class AdNetworkManager
{
    public function driver(): AdGateway
    {
        return new NullAdGateway;
    }
}
