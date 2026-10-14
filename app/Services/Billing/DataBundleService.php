<?php

namespace App\Services\Billing;

use App\Jobs\ProcessDataBundlePurchase;
use App\Models\AuditLog;
use App\Models\DataBundlePurchase;
use App\Models\User;
use App\Services\Billing\Gateways\MonnifyGateway;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DataBundleService
{
    public function __construct(private MonnifyBills $bills) {}

    private function gateway(): MonnifyGateway
    {
        abort_unless(config('services.payments.live') && filled(config('services.monnify.contract_code')), 503,
            'Enable Monnify checkout in Payment gateways before purchasing mobile data.');

        return new MonnifyGateway((string) config('services.monnify.api_key'), (string) config('services.monnify.secret'),
            (string) config('services.monnify.contract_code'), (string) config('services.monnify.base_url'));
    }

    public function purchase(User $user, array $input, string $key): DataBundlePurchase
    {
        $reference = 'data_'.hash('sha256', $user->id.'|'.$key);

        return Cache::lock('data-create:'.$reference, 120)->block(2, function () use ($user, $input, $reference) {
            if ($existing = DataBundlePurchase::where('payment_reference', $reference)->first()) {
                abort_unless($existing->product_code === $input['product_code'] && $existing->biller_code === $input['biller_code']
                    && $existing->phone_number === $input['phone_number'] && $existing->amount_minor === $input['amount_minor'],
                    409, 'This purchase reference was already used for a different request.');

                app(DataPurchaseActivity::class)->record('checkout_reused', $existing);

                return $existing;
            }
            $gateway = $this->gateway();
            $product = collect($this->bills->products($input['biller_code'], fresh: true))->firstWhere('product_code', $input['product_code']);
            abort_unless($product && $product['price_type'] === 'FIXED' && $product['amount_minor'] > 0, 422, 'Choose an available fixed-price data plan.');
            abort_unless($product['amount_minor'] === $input['amount_minor'], 422, 'This plan price has changed. Refresh the catalogue before paying.');
            $this->bills->validate($product['product_code'], $input['phone_number']);
            $purchase = DataBundlePurchase::create([
                'user_id' => $user->id, 'operator' => $input['biller_code'], 'biller_code' => $input['biller_code'],
                'product_code' => $product['product_code'], 'product_name' => $product['name'],
                'phone_number' => $input['phone_number'], 'amount_minor' => $product['amount_minor'],
                'status' => 'awaiting_payment', 'consent_at' => now(), 'payment_reference' => $reference,
                'vend_reference' => 'vend_'.$reference,
            ]);
            app(DataPurchaseActivity::class)->record('purchase_created', $purchase);
            try {
                $checkout = $gateway->initialize($reference, $purchase->amount_minor, (string) $user->email,
                    ['purpose' => 'data_bundle', 'purchase_id' => $purchase->id]);
                if (! $checkout->checkoutUrl) {
                    throw new HttpException(502, 'Monnify did not return a checkout URL.');
                }
                $purchase->update(['checkout_url' => $checkout->checkoutUrl, 'gateway_txn_ref' => $checkout->providerReference]);
            } catch (\Throwable $error) {
                // Initialization can time out after Monnify accepts it. Retain the reference for reconciliation.
                ProcessDataBundlePurchase::dispatch($purchase->id)->delay(now()->addSeconds(30));
                app(DataPurchaseActivity::class)->record('checkout_uncertain', $purchase, ['error_type' => class_basename($error)]);

                return $purchase;
            }
            app(DataPurchaseActivity::class)->record('checkout_created', $purchase);
            ProcessDataBundlePurchase::dispatch($purchase->id)->delay(now()->addSeconds(30));

            return $purchase;
        });
    }

    /** Verify payment on the server, then vend once. An uncertain vend is only requeried. */
    public function refresh(DataBundlePurchase $purchase): DataBundlePurchase
    {
        $lock = Cache::lock('data-process:'.$purchase->id, 120);
        if (! $lock->get()) {
            return $purchase->refresh();
        }
        try {
            $purchase->refresh();
            // An older paid order may have been held only because Monnify's
            // verification returned `currency` instead of `currencyCode`.
            // Recheck that precise case, but never revive a refunded order or
            // retry a vend that may already have reached the network.
            $recheckPaymentMismatch = $purchase->status === 'needs_review'
                && ! $purchase->paid_at && ! $purchase->vend_started_at
                && AuditLog::where('subject_type', $purchase->getMorphClass())->where('subject_id', $purchase->id)
                    ->where('action', 'billing.data_bundle.payment_mismatch')->exists()
                && ! AuditLog::where('subject_type', $purchase->getMorphClass())->where('subject_id', $purchase->id)
                    ->where('action', 'billing.data_bundle.refund_received')->exists();
            if ((! in_array($purchase->status, ['awaiting_payment', 'processing'], true) && ! $recheckPaymentMismatch)
                || ! $purchase->payment_reference) {
                return $purchase;
            }
            if (! $purchase->paid_at) {
                app(DataPurchaseActivity::class)->record('payment_verification_started', $purchase);
                $verified = $this->gateway()->verify($purchase->payment_reference);
                app(DataPurchaseActivity::class)->record('payment_verification_result', $purchase, ['provider_status' => $verified->status]);
                if ($verified->status === 'failed') {
                    $purchase->update(['status' => 'payment_failed']);
                    app(DataPurchaseActivity::class)->record('payment_failed', $purchase);

                    return $purchase;
                }
                if ($verified->status !== 'success') {
                    return $purchase;
                }
                $currency = $verified->raw['responseBody']['currencyCode'] ?? $verified->raw['responseBody']['currency'] ?? null;
                if ($verified->amountMinor !== $purchase->amount_minor || $currency !== 'NGN'
                    || ($verified->raw['responseBody']['paymentReference'] ?? null) !== $purchase->payment_reference) {
                    $purchase->update(['status' => 'needs_review']);
                    app(DataPurchaseActivity::class)->record('payment_mismatch', $purchase, [
                        'provider_amount_minor' => $verified->amountMinor,
                        'provider_currency' => $currency,
                        'reference_matches' => ($verified->raw['responseBody']['paymentReference'] ?? null) === $purchase->payment_reference,
                    ]);

                    return $purchase;
                }
                $purchase->update(['paid_at' => now(), 'status' => 'processing']);
                app(DataPurchaseActivity::class)->record('payment_confirmed', $purchase);
            }
            if ($purchase->vend_started_at) {
                app(DataPurchaseActivity::class)->record('delivery_requery_started', $purchase);
                $result = $this->bills->requery($purchase->vend_reference);
            } else {
                app(DataPurchaseActivity::class)->record('recipient_validation_started', $purchase);
                $validation = $this->bills->validate($purchase->product_code, $purchase->phone_number);
                app(DataPurchaseActivity::class)->record('recipient_validated', $purchase);
                $instruction = $validation['vendInstruction'] ?? null;
                if (! is_array($instruction) || ! is_bool($instruction['requireValidationRef'] ?? null)) {
                    throw new HttpException(502, 'Monnify did not return vending instructions.');
                }
                $payload = [
                    'productCode' => $purchase->product_code, 'customerId' => $purchase->phone_number,
                    'vendAmount' => round($purchase->amount_minor / 100, 2), 'vendReference' => $purchase->vend_reference,
                    'emailAddress' => $purchase->user->email, 'phoneNumber' => $purchase->phone_number,
                ];
                if ($instruction['requireValidationRef']) {
                    abort_unless(filled($instruction['validationReference'] ?? null), 502, 'Monnify did not return a required validation reference.');
                    $payload['validationReference'] = $instruction['validationReference'];
                }
                // Persist before HTTP: crashes/timeouts can never trigger a second vend.
                $purchase->update(['vend_started_at' => now()]);
                app(DataPurchaseActivity::class)->record('delivery_started', $purchase);
                $result = $this->bills->vend($payload);
            }
            $status = match ($result['vendStatus'] ?? null) {
                'SUCCESS', 'SUCCESSFUL' => 'success',
                'FAILED' => 'failed',
                default => 'processing',
            };
            // Monnify documents vendStatus and vendReference on success. Some
            // responses also echo product, recipient and amount; check those
            // when present, without rejecting a valid minimal response.
            if ($status === 'success' && (($result['vendReference'] ?? null) !== $purchase->vend_reference
                || (isset($result['productCode']) && $result['productCode'] !== $purchase->product_code)
                || (isset($result['customerId']) && $result['customerId'] !== $purchase->phone_number)
                || (isset($result['vendAmount']) && (int) round((float) $result['vendAmount'] * 100) !== $purchase->amount_minor))) {
                $status = 'needs_review';
            }
            app(DataPurchaseActivity::class)->record('delivery_result', $purchase, [
                'result_status' => $status,
                'provider_vend_status' => is_string($result['vendStatus'] ?? null) ? $result['vendStatus'] : null,
            ]);
            if ($status !== 'processing') {
                $purchase->update(['status' => $status]);
                app(DataPurchaseActivity::class)->record($status, $purchase);
            }

            return $purchase;
        } catch (\Throwable $error) {
            $details = ['error_type' => class_basename($error)];
            if ($error instanceof MonnifyBillsRequestException) {
                $details += [
                    'endpoint' => $error->endpoint,
                    'provider_http_status' => $error->providerHttpStatus,
                    'provider_code' => $error->providerCode,
                    // Keep provider text out of the customer response and redact
                    // recipient/contact details before recording it for support.
                    'provider_message' => mb_substr(preg_replace([
                        '/\b0\d{10}\b/', '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                    ], '[redacted]', $error->providerMessage ?? '') ?? '', 0, 200),
                ];
            }
            app(DataPurchaseActivity::class)->record('processing_error', $purchase, $details);
            throw $error;
        } finally {
            $lock->release();
        }
    }

    public function response(DataBundlePurchase $purchase): array
    {
        return [
            'purchase_id' => $purchase->id, 'status' => $purchase->status, 'amount_minor' => $purchase->amount_minor,
            'product_name' => $purchase->product_name, 'phone_number' => $purchase->phone_number,
            'checkout_url' => $purchase->checkout_url, 'payment_reference' => $purchase->payment_reference,
            'payment_confirmed' => $purchase->paid_at !== null, 'delivery_started' => $purchase->vend_started_at !== null,
        ];
    }
}
