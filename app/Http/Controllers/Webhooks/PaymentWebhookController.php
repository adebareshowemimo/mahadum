<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function paystack(Request $request): JsonResponse
    {
        $secret = config('services.paystack.secret');
        $signature = $request->header('x-paystack-signature');

        abort_unless(
            $secret && hash_equals(hash_hmac('sha512', $request->getContent(), $secret), (string) $signature),
            401,
            'Invalid signature.',
        );

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];
        // charge.success carries `reference`; refund events carry `transaction_reference`.
        $reference = $data['reference'] ?? $data['transaction_reference'] ?? null;

        // Anything not explicitly recognised is recorded but moves no money.
        $kind = match (true) {
            $event === 'charge.success' && ($data['status'] ?? null) === 'success' => 'success',
            $event === 'refund.processed' => 'refund',
            $event === 'charge.failed' => 'failed',
            default => 'ignored',
        };

        // Refund events carry their own data.id (distinct from the charge), so they
        // get a separate idempotency key while correlating to the same reference.
        $eventKey = (string) ($data['id'] ?? $reference ?? $request->getContent());

        $outcome = $this->payments->process('paystack', $eventKey, $reference, $kind, $data['amount'] ?? null, $payload);

        return response()->json(['status' => $outcome]);
    }

    public function flutterwave(Request $request): JsonResponse
    {
        $expected = config('services.flutterwave.secret_hash');

        abort_unless(
            $expected && hash_equals($expected, (string) $request->header('verif-hash')),
            401,
            'Invalid signature.',
        );

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];
        $reference = $data['tx_ref'] ?? null;

        // Anything not explicitly recognised is recorded but moves no money.
        $kind = match (true) {
            ($data['status'] ?? null) === 'successful' => 'success',
            $event === 'charge.refund' || ($data['status'] ?? null) === 'REFUNDED' => 'refund',
            ($data['status'] ?? null) === 'failed' => 'failed',
            default => 'ignored',
        };

        // Flutterwave reports amount in major units.
        $amountMinor = isset($data['amount']) ? (int) round(((float) $data['amount']) * 100) : null;
        $eventKey = (string) ($data['id'] ?? $reference ?? $request->getContent());

        $outcome = $this->payments->process('flutterwave', $eventKey, $reference, $kind, $amountMinor, $payload);

        return response()->json(['status' => $outcome]);
    }

    public function monnify(Request $request): JsonResponse
    {
        $secret = config('services.monnify.secret');
        // Monnify signs the raw body with HMAC-SHA512 using the secret key.
        $signature = $request->header('monnify-signature');

        abort_unless(
            $secret && hash_equals(hash_hmac('sha512', $request->getContent(), (string) $secret), (string) $signature),
            401,
            'Invalid signature.',
        );

        $payload = $request->json()->all();
        $event = $payload['eventType'] ?? null;
        $data = $payload['eventData'] ?? [];
        // Our correlation key is `paymentReference` (passed at init); Monnify's own
        // `transactionReference` is the fallback for events that omit it.
        $reference = $data['paymentReference'] ?? $data['transactionReference'] ?? null;

        // Anything not explicitly recognised is recorded but moves no money.
        $kind = match (true) {
            $event === 'SUCCESSFUL_TRANSACTION' && ($data['paymentStatus'] ?? null) === 'PAID' => 'success',
            $event === 'SUCCESSFUL_REFUND' && ($data['refundStatus'] ?? null) === 'COMPLETED' => 'refund',
            default => 'ignored',
        };

        // Monnify reports amounts in major units (Naira); refunds carry refundAmount.
        $amountRaw = $data['amountPaid'] ?? $data['refundAmount'] ?? null;
        $amountMinor = $amountRaw !== null ? (int) round(((float) $amountRaw) * 100) : null;

        // Refunds carry their own refundReference (distinct from the charge), so they
        // get a separate idempotency key while correlating to the same reference.
        $eventKey = (string) ($data['refundReference'] ?? $data['transactionReference'] ?? $reference ?? $request->getContent());

        $outcome = $this->payments->process('monnify', $eventKey, $reference, $kind, $amountMinor, $payload);

        return response()->json(['status' => $outcome]);
    }
}
