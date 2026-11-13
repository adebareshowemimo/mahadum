<?php

namespace App\Services\Billing\Gateways;

class ReturnUrl
{
    public static function fromMetadata(array $metadata): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return match ($metadata['purpose'] ?? null) {
            'wallet_funding' => $frontend.'/payment/wallet/'.rawurlencode((string) $metadata['funding_id']),
            'subscription' => $frontend.'/payment/subscription/'.rawurlencode((string) $metadata['subscription_id']),
            'story' => $frontend.'/payment/story/'.rawurlencode((string) $metadata['purchase_id']),
            'invoice' => $frontend.'/payment/invoice/'.rawurlencode((string) $metadata['organization_id']).'/'.rawurlencode((string) $metadata['invoice_id']),
            'data_bundle' => rtrim((string) config('app.url'), '/').'/billing/data/status',
            default => (string) config('app.url'),
        };
    }
}
