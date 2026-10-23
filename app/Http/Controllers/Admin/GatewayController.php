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
 * Operations console for payment gateways. Credentials may come from
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
            'data_default_provider' => (string) config('services.data_bundles.default_provider', 'monnify'),
            'subscription_default_provider' => (string) config('services.subscriptions.default_provider', 'monnify'),
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
                    'configured' => filled(config('services.flutterwave.public_key'))
                        && filled(config('services.flutterwave.secret'))
                        && filled(config('services.flutterwave.encryption_key'))
                        && filled(config('services.flutterwave.secret_hash')),
                    'is_default' => config('services.payments.default') === 'flutterwave',
                    'enabled' => (bool) config('services.flutterwave.enabled'),
                    'bills_enabled' => (bool) config('services.flutterwave.bills_enabled'),
                    'environment' => str_starts_with((string) config('services.flutterwave.secret'), 'FLWSECK_TEST-') ? 'sandbox' : 'live',
                    'webhook_url' => url('/api/v1/webhooks/flutterwave'),
                    'requirements' => [
                        ['label' => 'Public key', 'env' => 'FLUTTERWAVE_PUBLIC_KEY', 'set' => filled(config('services.flutterwave.public_key'))],
                        ['label' => 'Secret key', 'env' => 'FLUTTERWAVE_SECRET', 'set' => filled(config('services.flutterwave.secret'))],
                        ['label' => 'Encryption key', 'env' => 'FLUTTERWAVE_ENCRYPTION_KEY', 'set' => filled(config('services.flutterwave.encryption_key'))],
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
            'flutterwave' => $this->pingFlutterwaveBills(),
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

    public function updateFlutterwave(Request $request): JsonResponse
    {
        $this->payments->apply();
        $clean = $request->validate([
            'enabled' => ['required', 'boolean'],
            'bills_enabled' => ['required', 'boolean'],
            'public_key' => ['nullable', 'string', 'max:1000'],
            'secret' => ['nullable', 'string', 'max:1000'],
            'encryption_key' => ['nullable', 'string', 'max:1000'],
            'secret_hash' => ['nullable', 'string', 'max:1000'],
        ]);

        $publicKey = filled($clean['public_key'] ?? null) ? $clean['public_key'] : config('services.flutterwave.public_key');
        $secret = filled($clean['secret'] ?? null) ? $clean['secret'] : config('services.flutterwave.secret');
        $encryptionKey = filled($clean['encryption_key'] ?? null) ? $clean['encryption_key'] : config('services.flutterwave.encryption_key');
        $secretChanged = filled($clean['secret'] ?? null) && $clean['secret'] !== config('services.flutterwave.secret');
        $secretHash = filled($clean['secret_hash'] ?? null) ? $clean['secret_hash']
            : ($secretChanged ? '' : config('services.flutterwave.secret_hash'));
        if ($secretChanged && (! filled($clean['public_key'] ?? null) || ! filled($clean['encryption_key'] ?? null))) {
            throw ValidationException::withMessages([
                'public_key' => ['Enter the matching public and encryption keys when replacing the secret key.'],
            ]);
        }
        if (filled($publicKey) && filled($secret) && filled($encryptionKey)
            && count(array_unique([
                str_contains((string) $publicKey, '_TEST'),
                str_contains((string) $secret, '_TEST'),
                str_contains((string) $encryptionKey, '_TEST'),
            ])) !== 1) {
            throw ValidationException::withMessages([
                'public_key' => ['Use three keys from the same Flutterwave test or live account mode.'],
            ]);
        }
        if ($clean['enabled'] && (! filled($publicKey) || ! filled($secret) || ! filled($encryptionKey) || ! filled($secretHash))) {
            throw ValidationException::withMessages([
                'secret' => ['All three API keys and a separate webhook hash are required before Flutterwave checkout can be enabled.'],
            ]);
        }
        if ($clean['bills_enabled'] && ! $clean['enabled']) {
            throw ValidationException::withMessages([
                'bills_enabled' => ['Enable Flutterwave checkout before enabling data purchases.'],
            ]);
        }

        $before = [
            'enabled' => (bool) config('services.flutterwave.enabled'),
            'bills_enabled' => (bool) config('services.flutterwave.bills_enabled'),
            'configured' => filled(config('services.flutterwave.public_key')) && filled(config('services.flutterwave.secret'))
                && filled(config('services.flutterwave.encryption_key')) && filled(config('services.flutterwave.secret_hash')),
        ];
        $values = [
            'flutterwave.enabled' => (bool) $clean['enabled'],
            'flutterwave.bills_enabled' => (bool) $clean['bills_enabled'],
        ];
        if (filled($clean['public_key'] ?? null)) {
            $values['flutterwave.public_key'] = $clean['public_key'];
        }
        if (filled($clean['secret'] ?? null)) {
            $values['flutterwave.secret'] = $clean['secret'];
        }
        if (filled($clean['encryption_key'] ?? null)) {
            $values['flutterwave.encryption_key'] = $clean['encryption_key'];
        }
        if (filled($clean['secret_hash'] ?? null)) {
            $values['flutterwave.secret_hash'] = $clean['secret_hash'];
        } elseif ($secretChanged) {
            // Do not reuse a webhook hash from a different test/live key set.
            $values['flutterwave.secret_hash'] = '';
        }

        $this->settings->set($values);
        $this->payments->apply();
        $after = [
            'enabled' => (bool) config('services.flutterwave.enabled'),
            'bills_enabled' => (bool) config('services.flutterwave.bills_enabled'),
            'configured' => filled(config('services.flutterwave.public_key')) && filled(config('services.flutterwave.secret'))
                && filled(config('services.flutterwave.encryption_key')) && filled(config('services.flutterwave.secret_hash')),
        ];
        $this->audit->record('payment.flutterwave_configuration_updated', null, $before, $after);

        return $this->index();
    }

    public function updateDataDefault(Request $request): JsonResponse
    {
        $clean = $request->validate(['provider' => ['required', 'in:monnify,flutterwave']]);
        $before = ['provider' => $this->settings->get('data_bundles.default_provider', config('services.data_bundles.default_provider', 'monnify'))];
        $this->settings->set(['data_bundles.default_provider' => $clean['provider']]);
        $this->payments->apply();
        $this->audit->record('billing.data_bundle.default_provider_updated', null, $before, ['provider' => $clean['provider']]);

        return $this->index();
    }

    public function updateSubscriptionDefault(Request $request): JsonResponse
    {
        $clean = $request->validate(['provider' => ['required', 'in:monnify,paystack,flutterwave']]);
        $before = ['provider' => $this->settings->get('subscriptions.default_provider', config('services.subscriptions.default_provider', 'monnify'))];
        $this->settings->set(['subscriptions.default_provider' => $clean['provider']]);
        $this->payments->apply();
        $this->audit->record('billing.subscription.default_provider_updated', null, $before, ['provider' => $clean['provider']]);

        return $this->index();
    }

    private function pingFlutterwaveBills(): JsonResponse
    {
        $secret = config('services.flutterwave.secret');
        if (blank($secret)) {
            return response()->json(['data' => ['ok' => false, 'message' => 'Save a Flutterwave secret key before testing the data catalogue.']]);
        }

        try {
            $response = Http::withToken((string) $secret)->acceptJson()->timeout(10)
                ->get(rtrim((string) config('services.flutterwave.base_url'), '/').'/top-bill-categories', ['country' => 'NG']);
            $categories = $response->json('data');
            $hasMobileData = $response->successful() && is_array($categories)
                && collect($categories)->contains(fn (mixed $category) => is_array($category) && ($category['code'] ?? null) === 'MOBILEDATA');

            return response()->json(['data' => [
                'ok' => $hasMobileData,
                'message' => $hasMobileData
                    ? 'Flutterwave mobile data catalogue is available.'
                    : ($response->successful()
                        ? 'Flutterwave responded, but mobile data is not available for this account.'
                        : 'Flutterwave bill catalogue rejected the request (HTTP '.$response->status().'): '.(string) ($response->json('message') ?? 'Check bill access and IP whitelisting.')),
            ]]);
        } catch (\Throwable) {
            return response()->json(['data' => ['ok' => false, 'message' => 'Could not reach the Flutterwave bill catalogue.']]);
        }
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
