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
            'services.data_bundles.default_provider' => $this->settings->get('data_bundles.default_provider', config('services.data_bundles.default_provider', 'monnify')),
            'services.monnify.api_key' => $this->settings->get('monnify.api_key', config('services.monnify.api_key')),
            'services.monnify.secret' => $this->settings->get('monnify.secret', config('services.monnify.secret')),
            'services.monnify.contract_code' => $this->settings->get('monnify.contract_code', config('services.monnify.contract_code')),
            'services.monnify.base_url' => $this->settings->get('monnify.base_url', config('services.monnify.base_url')),
            'services.flutterwave.public_key' => $this->settings->get('flutterwave.public_key', config('services.flutterwave.public_key')),
            'services.flutterwave.secret' => $this->settings->get('flutterwave.secret', config('services.flutterwave.secret')),
            'services.flutterwave.encryption_key' => $this->settings->get('flutterwave.encryption_key', config('services.flutterwave.encryption_key')),
            'services.flutterwave.secret_hash' => $this->settings->get('flutterwave.secret_hash', config('services.flutterwave.secret_hash')),
            'services.flutterwave.enabled' => (bool) $this->settings->get('flutterwave.enabled', config('services.flutterwave.enabled')),
            'services.flutterwave.bills_enabled' => (bool) $this->settings->get('flutterwave.bills_enabled', config('services.flutterwave.bills_enabled')),
        ]);
    }

    public function monnifyEnvironment(): string
    {
        return str_contains((string) config('services.monnify.base_url'), 'sandbox') ? 'sandbox' : 'live';
    }
}
