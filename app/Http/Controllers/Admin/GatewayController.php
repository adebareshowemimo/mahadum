<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\IntegrationSettings;
use App\Services\PaymentConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Operations console for payment gateways. Monnify credentials may come from
 * environment variables or encrypted DB overrides and are never returned.
 */
class GatewayController extends Controller
{
    public function __construct(
        private IntegrationSettings $settings,
        private PaymentConfiguration $payments,
        private AuditLogger $audit,
    ) {}

    public function index(): JsonResponse
    {
        $this->payments->apply();

        return response()->json(['data' => [
            'live' => (bool) config('services.payments.live'),
            'default' => (string) config('services.payments.default', 'monnify'),
            'providers' => [
                [
                    'key' => 'monnify',
                    'label' => 'Monnify',
                    'configured' => filled(config('services.monnify.api_key'))
                        && filled(config('services.monnify.secret'))
                        && filled(config('services.monnify.contract_code')),
                    'is_default' => config('services.payments.default') === 'monnify',
                    'environment' => $this->payments->monnifyEnvironment(),
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
            // Airtime (VAS) billing runs through the operator SDP, not a card
            // gateway. Without live credentials TelcoGatewayManager serves a
            // NullTelcoGateway that succeeds deterministically and sends no SMS,
            // so report that plainly rather than letting it read as "working".
            'telco' => [
                'label' => 'Operator SDP (airtime billing)',
                'live' => (bool) config('services.telco.live'),
                'configured' => (bool) config('services.telco.live')
                    && filled(config('services.telco.base_url'))
                    && filled(config('services.telco.token')),
                'webhook_url' => url('/api/v1/webhooks/telco'),
                'requirements' => [
                    ['label' => 'Live mode', 'env' => 'TELCO_SDP_LIVE', 'set' => (bool) config('services.telco.live')],
                    ['label' => 'SDP base URL', 'env' => 'TELCO_SDP_BASE_URL', 'set' => filled(config('services.telco.base_url'))],
                    ['label' => 'SDP token', 'env' => 'TELCO_SDP_TOKEN', 'set' => filled(config('services.telco.token'))],
                    ['label' => 'Webhook secret', 'env' => 'TELCO_WEBHOOK_SECRET', 'set' => filled(config('services.telco.webhook_secret'))],
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
        $this->payments->apply();

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

    public function updateMonnify(Request $request): JsonResponse
    {
        $clean = $request->validate([
            'live' => ['required', 'boolean'],
            'environment' => ['required', 'in:sandbox,live'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:1000'],
            'contract_code' => ['nullable', 'string', 'max:255'],
        ]);

        $apiKey = filled($clean['api_key'] ?? null) ? $clean['api_key'] : config('services.monnify.api_key');
        $secret = filled($clean['secret'] ?? null) ? $clean['secret'] : config('services.monnify.secret');
        $contractCode = filled($clean['contract_code'] ?? null) ? $clean['contract_code'] : config('services.monnify.contract_code');

        if ($clean['live'] && (! filled($apiKey) || ! filled($secret) || ! filled($contractCode))) {
            throw ValidationException::withMessages([
                'api_key' => ['API key, secret key and contract code are required before live checkout can be enabled.'],
            ]);
        }

        $before = [
            'live' => (bool) config('services.payments.live'),
            'environment' => $this->payments->monnifyEnvironment(),
            'configured' => filled(config('services.monnify.api_key')) && filled(config('services.monnify.secret')) && filled(config('services.monnify.contract_code')),
        ];
        $values = [
            'payments.live' => (bool) $clean['live'],
            'payments.default' => 'monnify',
            'monnify.base_url' => $clean['environment'] === 'live'
                ? PaymentConfiguration::MONNIFY_LIVE_URL
                : PaymentConfiguration::MONNIFY_SANDBOX_URL,
        ];
        if (filled($clean['api_key'] ?? null)) {
            $values['monnify.api_key'] = $clean['api_key'];
        }
        if (filled($clean['secret'] ?? null)) {
            $values['monnify.secret'] = $clean['secret'];
        }
        if (filled($clean['contract_code'] ?? null)) {
            $values['monnify.contract_code'] = $clean['contract_code'];
        }

        $this->settings->set($values);
        $this->payments->apply();
        $after = [
            'live' => (bool) config('services.payments.live'),
            'environment' => $this->payments->monnifyEnvironment(),
            'configured' => filled(config('services.monnify.api_key')) && filled(config('services.monnify.secret')) && filled(config('services.monnify.contract_code')),
        ];
        $this->audit->record('payment.monnify_configuration_updated', null, $before, $after);

        return $this->index();
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
