<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Monnify's catalogue and VAS API; no fabricated catalogue or simulated vending. */
class MonnifyBills
{
    private ?string $token = null;

    public function billers(bool $fresh = false): array
    {
        $fetch = fn () => array_map(fn ($b) => [
            'code' => (string) $b['code'], 'name' => (string) $b['name'],
        ], $this->pages('billers', ['category_code' => 'DATA_BUNDLE']));

        return $fresh ? $fetch() : Cache::remember($this->cacheKey('billers:DATA_BUNDLE'), 300, $fetch);
    }

    public function products(string $billerCode, bool $fresh = false): array
    {
        abort_unless(collect($this->billers())->contains('code', $billerCode), 422, 'Choose an available data network.');
        $fetch = fn () => array_values(array_map(fn ($p) => [
            'product_code' => (string) $p['code'],
            'biller_code' => $billerCode,
            'name' => (string) $p['name'],
            'amount_minor' => isset($p['price']) ? (int) round((float) $p['price'] * 100) : null,
            'price_type' => $p['priceType'] ?? 'OPEN',
            'currency' => 'NGN',
            'duration' => $p['metadata']['duration'] ?? null,
            'duration_unit' => $p['metadata']['durationUnit'] ?? null,
        ], array_filter($this->pages('biller-products', ['biller_code' => $billerCode]),
            fn ($p) => ($p['category']['code'] ?? null) === 'DATA_BUNDLE')));

        return $fresh ? $fetch() : Cache::remember($this->cacheKey('products:'.$billerCode), 300, $fetch);
    }

    public function validate(string $productCode, string $phone): array
    {
        return $this->request('post', 'validate-customer', ['productCode' => $productCode, 'customerId' => $phone]);
    }

    public function vend(array $payload): array
    {
        // Never retry a money request after a timeout. Requery its persisted reference instead.
        return $this->request('post', 'vend', $payload);
    }

    public function requery(string $reference): array
    {
        return $this->request('get', 'requery', ['vendReference' => $reference]);
    }

    private function pages(string $endpoint, array $query): array
    {
        $items = [];
        for ($page = 0; $page < 100; $page++) {
            $body = $this->request('get', $endpoint, $query + ['page' => $page, 'size' => 100]);
            if (! isset($body['content']) || ! is_array($body['content'])) {
                throw new HttpException(502, 'Monnify returned an invalid catalogue response.');
            }
            $items = array_merge($items, $body['content']);
            if (($body['last'] ?? false) || count($body['content']) === 0
                || (isset($body['totalPages']) && $page + 1 >= (int) $body['totalPages'])
                || (array_key_exists('nextPage', $body) && $body['nextPage'] === null)
                || (isset($body['totalElements']) && count($items) >= (int) $body['totalElements'])
                || (! array_key_exists('totalPages', $body) && ! array_key_exists('last', $body)
                    && ! array_key_exists('nextPage', $body) && ! array_key_exists('totalElements', $body)
                    && count($body['content']) < 100)) {
                return $items;
            }
        }
        throw new HttpException(502, 'Monnify catalogue pagination did not complete.');
    }

    private function cacheKey(string $suffix): string
    {
        return 'monnify-bills:'.hash('sha256', implode('|', [
            config('services.monnify.base_url'), config('services.monnify.api_key'), config('services.monnify.secret'),
        ])).':'.$suffix;
    }

    private function request(string $method, string $endpoint, array $payload): array
    {
        $base = rtrim((string) config('services.monnify.base_url'), '/');
        if (! filled(config('services.monnify.api_key')) || ! filled(config('services.monnify.secret'))) {
            throw new HttpException(503, 'Configure Monnify credentials in Payment gateways to load mobile data plans.');
        }
        try {
            if ($this->token === null) {
                $auth = Http::withBasicAuth((string) config('services.monnify.api_key'), (string) config('services.monnify.secret'))
                    ->acceptJson()->connectTimeout(5)->timeout(15)->post($base.'/api/v1/auth/login');
                $this->token = $auth->successful() ? $auth->json('responseBody.accessToken') : null;
                if (! filled($this->token)) {
                    throw new HttpException(503, 'Monnify authentication failed. Check the configured credentials.');
                }
            }
            $response = Http::withToken($this->token)->acceptJson()->connectTimeout(5)->timeout(15)
                ->{$method}($base.'/api/v1/vas/bills-payment/'.$endpoint, $payload);
            if (! $response->successful() || $response->json('requestSuccessful') !== true) {
                throw new HttpException(502, in_array($response->status(), [401, 403, 406], true)
                    ? 'Mobile data plans are unavailable because Monnify Bills Payment access is not enabled or was rejected. Please contact support.'
                    : 'Monnify’s Bills Payment service is unavailable. Please try again later.');
            }
            $body = $response->json('responseBody');
            if (! is_array($body)) {
                throw new HttpException(502, 'Monnify returned an invalid response.');
            }

            return $body;
        } catch (ConnectionException) {
            throw new HttpException(503, 'Monnify is unavailable. Please try again later.');
        }
    }
}
