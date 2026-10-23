<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Nigerian mobile-data catalogue and bill purchases through Flutterwave v3. */
class FlutterwaveBills
{
    public function billers(bool $fresh = false): array
    {
        $fetch = function (): array {
            $categories = $this->request('get', 'top-bill-categories', ['country' => 'NG']);
            abort_unless(collect($categories)->contains('code', 'MOBILEDATA'), 503, 'Flutterwave mobile data is unavailable for this account.');

            return array_values(array_map(fn (array $b) => [
                'code' => (string) $b['biller_code'], 'name' => (string) $b['name'],
            ], array_filter($this->request('get', 'bills/MOBILEDATA/billers', ['country' => 'NG']),
                fn ($b) => is_array($b) && filled($b['biller_code'] ?? null) && filled($b['name'] ?? null))));
        };

        if ($fresh) {
            $billers = $fetch();
            Cache::put($this->cacheKey('billers'), $billers, 300);

            return $billers;
        }

        return Cache::remember($this->cacheKey('billers'), 300, $fetch);
    }

    public function products(string $billerCode, bool $fresh = false): array
    {
        abort_unless(collect($this->billers())->contains('code', $billerCode), 422, 'Choose an available data network.');
        $fetch = function () use ($billerCode): array {
            $items = $this->request('get', 'billers/'.rawurlencode($billerCode).'/items', []);

            return array_values(array_map(fn (array $p) => [
                'product_code' => (string) $p['item_code'], 'biller_code' => $billerCode,
                'name' => (string) $p['name'],
                'amount_minor' => (int) round((float) $p['amount'] * 100),
                'price_type' => 'FIXED', 'currency' => 'NGN',
                'duration' => null, 'duration_unit' => null,
            ], array_filter($items, fn ($p) => is_array($p) && filled($p['item_code'] ?? null)
                && filled($p['name'] ?? null) && is_numeric($p['amount'] ?? null) && (float) $p['amount'] > 0)));
        };

        return $fresh ? $fetch() : Cache::remember($this->cacheKey('products:'.$billerCode), 300, $fetch);
    }

    /** The API explicitly waives recipient validation for mobile data. */
    public function validate(string $productCode, string $phone): array
    {
        return ['requireValidationRef' => false];
    }

    public function vend(array $payload): array
    {
        $biller = rawurlencode((string) $payload['biller_code']);
        $item = rawurlencode((string) $payload['product_code']);
        $data = $this->request('post', "billers/{$biller}/items/{$item}/payment", [
            'country' => 'NG', 'customer_id' => $payload['phone_number'],
            'amount' => $payload['amount_minor'] / 100,
            'reference' => $payload['reference'],
        ]);

        // A successful HTTP response may still represent a pending bill. Only
        // the status endpoint can confirm fulfillment to the recipient.
        return ['vendStatus' => 'PENDING', 'vendReference' => $payload['reference'],
            'bill_tx_ref' => $data['tx_ref'] ?? null];
    }

    public function requery(string $reference, ?string $customerReference = null): array
    {
        if ($customerReference === null) {
            // An uncertain POST did not return Flutterwave's bill tx_ref.
            // Never resubmit. A signed bill webhook or support reconciliation
            // can later supply it.
            return ['vendStatus' => 'PENDING', 'vendReference' => $reference];
        }
        $data = $this->request('get', 'bills/'.rawurlencode($reference), ['verbose' => 1], allowNotFound: true);
        if ($data === []) {
            return ['vendStatus' => 'PENDING', 'vendReference' => $customerReference];
        }
        $status = strtolower((string) ($data['status'] ?? 'pending'));

        return [
            'vendStatus' => match ($status) {
                'success', 'successful', 'completed' => 'SUCCESS',
                'failed', 'failure' => 'FAILED',
                default => 'PENDING',
            },
            'vendReference' => $data['customer_reference'] ?? $data['reference'] ?? null,
            'customerId' => $data['customer_id'] ?? $data['customer'] ?? null,
            'vendAmount' => $data['amount'] ?? null,
        ];
    }

    private function cacheKey(string $suffix): string
    {
        return 'flutterwave-bills:'.hash('sha256', (string) config('services.flutterwave.secret')).':'.$suffix;
    }

    private function request(string $method, string $path, array $payload, bool $allowNotFound = false): array
    {
        $secret = (string) config('services.flutterwave.secret');
        abort_unless($secret !== '', 503, 'Configure Flutterwave before loading data plans.');
        $url = rtrim((string) config('services.flutterwave.base_url'), '/').'/'.$path;
        try {
            $response = Http::withToken($secret)->acceptJson()->connectTimeout(5)->timeout(15)->{$method}($url, $payload);
        } catch (ConnectionException) {
            throw new HttpException(503, 'Flutterwave is unavailable. Please try again later.');
        }
        if ($allowNotFound && $response->status() === 404) {
            return [];
        }
        if (! $response->successful() || $response->json('status') !== 'success') {
            throw new HttpException(in_array($response->status(), [401, 403], true) ? 503 : 502,
                'Flutterwave could not complete the data request. Check the order status or contact support.');
        }
        $data = $response->json('data');
        if (! is_array($data)) {
            throw new HttpException(502, 'Flutterwave returned an invalid data response.');
        }

        return $data;
    }
}
