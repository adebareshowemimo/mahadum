<?php

namespace App\Services\Billing;

use App\Services\Billing\Gateways\FlutterwaveGateway;
use App\Services\Billing\Gateways\MonnifyGateway;
use App\Services\Billing\Gateways\NullGateway;
use App\Services\Billing\Gateways\PaymentGateway;
use App\Services\Billing\Gateways\PaystackGateway;

/**
 * Resolves the outbound payment gateway by name. Returns a NullGateway (no HTTP,
 * null checkout URL) unless `services.payments.live` is on — so local/CI never
 * make live calls and the live path is opt-in per environment.
 */
class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGateway
    {
        if (! config('services.payments.live')) {
            return new NullGateway;
        }

        $name ??= (string) config('services.payments.default', 'monnify');

        return match ($name) {
            'flutterwave' => new FlutterwaveGateway(
                (string) config('services.flutterwave.secret'),
                (string) config('services.flutterwave.base_url'),
            ),
            'paystack' => new PaystackGateway(
                (string) config('services.paystack.secret'),
                (string) config('services.paystack.base_url'),
            ),
            default => new MonnifyGateway(
                (string) config('services.monnify.api_key'),
                (string) config('services.monnify.secret'),
                (string) config('services.monnify.contract_code'),
                (string) config('services.monnify.base_url'),
            ),
        };
    }
}
