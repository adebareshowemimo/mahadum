<?php

namespace App\Services\Ads;

/**
 * Managed rewarded-video inventory is opt-in. Other placements stay unavailable
 * until a concrete network provider is configured.
 */
class AdNetworkManager
{
    public function driver(): AdGateway
    {
        return app(ManagedVideoGateway::class);
    }
}
