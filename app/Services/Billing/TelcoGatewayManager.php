<?php

namespace App\Services\Billing;

use App\Services\Billing\Telco\NullTelcoGateway;
use App\Services\Billing\Telco\SdpTelcoGateway;
use App\Services\Billing\Telco\TelcoGateway;

/**
 * Resolves the operator SDP gateway. Returns a NullTelcoGateway (no HTTP,
 * deterministic success) unless `services.telco.live` is on — so local/CI never
 * make live calls and the live path is opt-in per environment.
 */
class TelcoGatewayManager
{
    public function driver(): TelcoGateway
    {
        if (! config('services.telco.live')) {
            return new NullTelcoGateway;
        }

        return new SdpTelcoGateway(
            (string) config('services.telco.base_url'),
            (string) config('services.telco.token'),
        );
    }
}
