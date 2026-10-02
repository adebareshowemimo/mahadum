<?php

namespace App\Services\Billing;

use App\Models\DataBundlePurchase;
use App\Services\AuditLogger;

class DataPurchaseActivity
{
    public function record(string $event, DataBundlePurchase $purchase, array $details = []): void
    {
        app(AuditLogger::class)->record('billing.data_bundle.'.$event, $purchase, [], array_merge([
            'source' => 'server', 'user_id' => $purchase->user_id,
            'purchase_id' => $purchase->id, 'payment_reference' => $purchase->payment_reference,
            'biller_code' => $purchase->biller_code, 'product_code' => $purchase->product_code,
            'amount_minor' => $purchase->amount_minor, 'status' => $purchase->status,
            'recipient_last4' => substr((string) $purchase->phone_number, -4),
        ], $details));
    }
}
