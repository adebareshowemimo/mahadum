<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * Read-only operations console for the payment gateways. Secrets live in the
 * environment (PCI-safe: never in the app DB, never returned to the client) — this
 * only reports whether each provider is *configured*, the live/test mode, the
 * default, and the webhook URL to register. A test-connection performs a harmless
 * authenticated read against the provider to validate the key.
 */
class GatewayController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'live' => (bool) config('services.payments.live'),
            'default' => (string) config('services.payments.default', 'monnify'),
            'providers' => [
                [
                    'key' => 'monnify',
                    'label' => 'Monnify',
                    'configured' => filled(config('services.monnify.api_key')) && filled(config('services.monnify.secret')),
                    'is_default' => config('services.payments.default') === 'monnify',
                    'webhook_url' => url('/api/v1/webhooks/monnify'),
                    'requirements' => [
                        ['label' => 'API key', 'env' => 'MONNIFY_API_KEY', 'set' => filled(config('services.monnify.api_key'))],
                        ['label' => 'Secret key', 'env' => 'MONNIFY_SECRET', 'set' => filled(config('services.monnify.secret'))],
                        ['label' => 'Contract code', 'env' => 'MONNIFY_CONTRACT_CODE', 'set' => filled(config('services.monnify.contract_code'))],
                    ],
                ],
                [
                    'key' => 'paystack',
                    'label' => 'Paystack',
                    'configured' => filled(config('services.paystack.secret')),
                    'is_default' => config('services.payments.default') === 'paystack',
                    'webhook_url' => url('/api/v1/webhooks/paystack'),
                    'requirements' => [
                        ['label' => 'Secret key', 'env' => 'PAYSTACK_SECRET', 'set' => filled(config('services.paystack.secret'))],
                    ],
                ],
                [
                    'key' => 'flutterwave',
                    'label' => 'Flutterwave',
                    'configured' => filled(config('services.flutterwave.secret')),
                    'is_default' => config('services.payments.default') === 'flutterwave',
                    'webhook_url' => url('/api/v1/webhooks/flutterwave'),
                    'requirements' => [
                        ['label' => 'Secret key', 'env' => 'FLUTTERWAVE_SECRET', 'set' => filled(config('services.flutterwave.secret'))],
                        ['label' => 'Webhook hash', 'env' => 'FLUTTERWAVE_SECRET_HASH', 'set' => filled(config('services.flutterwave.secret_hash'))],
                    ],
                ],
            ],
        ]]);
    }

    /**
     * Validate a provider's credentials with a lightweight authenticated read.
     * Moves no money; returns { ok, message } so an admin can confirm setup.
     */
    public function test(string $provider): JsonResponse
    {
        return match ($provider) {
            'monnify' => $this->pingMonnify(),
            'paystack' => $this->ping(
                config('services.paystack.secret'),
                rtrim((string) config('services.paystack.base_url'), '/').'/transaction?perPage=1',
                (string) config('services.paystack.secret'),
            ),
            'flutterwave' => $this->ping(
                config('services.flutterwave.secret'),
                rtrim((string) config('services.flutterwave.base_url'), '/').'/payment-plans?page=1',
                (string) config('services.flutterwave.secret'),
            ),
            default => response()->json(['data' => ['ok' => false, 'message' => 'Unknown provider.']], 404),
        };
    }

    /**
     * Monnify uses two-step auth (Basic apiKey:secret → token), so its connection
     * test authenticates rather than reading a resource.
     */
    private function pingMonnify(): JsonResponse
    {
        $apiKey = config('services.monnify.api_key');
        $secret = config('services.monnify.secret');

        if (blank($apiKey) || blank($secret)) {
            return response()->json(['data' => ['ok' => false, 'message' => 'Not configured — set the API key and secret in the environment.']]);
        }

        try {
            $response = Http::withBasicAuth((string) $apiKey, (string) $secret)
                ->acceptJson()->timeout(10)
                ->post(rtrim((string) config('services.monnify.base_url'), '/').'/api/v1/auth/login');

            return response()->json(['data' => [
                'ok' => $response->successful(),
                'message' => $response->successful()
                    ? 'Credentials verified — Monnify authenticated successfully.'
                    : 'Monnify rejected the login (HTTP '.$response->status().'). Check the API key and secret.',
            ]]);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['ok' => false, 'message' => 'Could not reach Monnify: '.$e->getMessage()]]);
        }
    }

    private function ping(mixed $secret, string $url, string $token): JsonResponse
    {
        if (blank($secret)) {
            return response()->json(['data' => ['ok' => false, 'message' => 'Not configured — set the secret key in the environment.']]);
        }

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(10)->get($url);

            return response()->json(['data' => [
                'ok' => $response->successful(),
                'message' => $response->successful()
                    ? 'Credentials verified — the gateway responded successfully.'
                    : 'The gateway rejected the request (HTTP '.$response->status().'). Check the secret key.',
            ]]);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['ok' => false, 'message' => 'Could not reach the gateway: '.$e->getMessage()]]);
        }
    }
}
