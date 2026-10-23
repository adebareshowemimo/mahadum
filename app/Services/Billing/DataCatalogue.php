<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Cache;

class DataCatalogue
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private MonnifyBills $monnify,
        private FlutterwaveBills $flutterwave,
    ) {}

    private function bills(string $provider): MonnifyBills|FlutterwaveBills
    {
        return $provider === 'flutterwave' ? $this->flutterwave : $this->monnify;
    }

    /** @return list<string> */
    public function providers(): array
    {
        $configured = $this->gateways->available();
        $providers = array_values(array_filter(['monnify', 'flutterwave'], function (string $provider) use ($configured): bool {
            if (! config('services.'.$provider.'.bills_enabled') || ! in_array($provider, $configured, true)) {
                return false;
            }

            $fingerprint = hash('sha256', (string) config('services.'.$provider.'.secret').(string) config('services.'.$provider.'.base_url'));

            return Cache::remember('data-catalogue-health:'.$provider.':'.$fingerprint, 60, function () use ($provider): bool {
                try {
                    // A fresh, read-only catalogue request confirms bill access and network availability.
                    return count($this->bills($provider)->billers(fresh: true)) > 0;
                } catch (\Throwable) {
                    return false;
                }
            });
        }));
        $preferred = config('services.data_bundles.default_provider', 'monnify');
        usort($providers, fn (string $a, string $b) => ($a === $preferred ? -1 : 0) <=> ($b === $preferred ? -1 : 0));

        return $providers;
    }

    private function network(string $name, string $code, string $provider): array
    {
        $text = $name.' '.$code;
        foreach (['MTN' => '/\bmtn\b/i', 'Airtel' => '/\bairtel\b/i', 'Glo' => '/\b(glo|globacom)\b/i', '9mobile' => '/\b(9\s*mobile|t2)\b/i'] as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                return ['code' => $label, 'name' => $label];
            }
        }

        // Unknown operators stay distinct so unrelated provider catalogues cannot be merged.
        return ['code' => $provider.':'.$code, 'name' => $name];
    }

    /** @return list<array{code: string, name: string}> */
    public function networks(): array
    {
        $networks = [];
        foreach ($this->providers() as $provider) {
            try {
                foreach ($this->bills($provider)->billers() as $biller) {
                    $network = $this->network($biller['name'], $biller['code'], $provider);
                    $networks[$network['code']] = $network;
                }
            } catch (\Throwable) {
                // Another provider can still serve the customer.
            }
        }

        return array_values($networks);
    }

    /** @return list<array<string, mixed>> */
    public function offers(string $networkCode): array
    {
        $offers = [];
        foreach ($this->providers() as $provider) {
            try {
                foreach ($this->bills($provider)->billers() as $biller) {
                    if ($this->network($biller['name'], $biller['code'], $provider)['code'] !== $networkCode) {
                        continue;
                    }
                    foreach ($this->bills($provider)->products($biller['code']) as $product) {
                        $offers[] = $product + ['provider' => $provider];
                    }
                }
            } catch (\Throwable) {
                // Skip a catalogue that stopped responding after its health check.
            }
        }

        return $offers;
    }
}
