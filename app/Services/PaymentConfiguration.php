<?php

namespace App\Services;

class PaymentConfiguration
{
    public const MONNIFY_SANDBOX_URL = 'https://sandbox.monnify.com';

    public const MONNIFY_LIVE_URL = 'https://api.monnify.com';

    public function __construct(private IntegrationSettings $settings) {}

    public function apply(): void
    {
        config([
            'services.payments.live' => (bool) $this->settings->get('payments.live', config('services.payments.live')),
            'services.payments.default' => $this->settings->get('payments.default', config('services.payments.default', 'monnify')),
            'services.monnify.api_key' => $this->settings->get('monnify.api_key', config('services.monnify.api_key')),
            'services.monnify.secret' => $this->settings->get('monnify.secret', config('services.monnify.secret')),
            'services.monnify.contract_code' => $this->settings->get('monnify.contract_code', config('services.monnify.contract_code')),
            'services.monnify.base_url' => $this->settings->get('monnify.base_url', config('services.monnify.base_url')),
        ]);
    }

    public function monnifyEnvironment(): string
    {
        return str_contains((string) config('services.monnify.base_url'), 'sandbox') ? 'sandbox' : 'live';
    }
}
