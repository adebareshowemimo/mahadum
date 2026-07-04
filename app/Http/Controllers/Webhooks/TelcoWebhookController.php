<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\TelcoBillingAttempt;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelcoWebhookController extends Controller
{
    /**
     * Delivery/billing result from the telco SDP. Signature-verified (HMAC-SHA256
     * of the raw body, matching the payment webhooks) and idempotent on
     * operator_ref. Correlates by operator_ref to the pending billing attempt and
     * records the outcome; a successful charge reactivates the subscription and
     * advances the next attempt.
     */
    public function dlr(Request $request): JsonResponse
    {
        $secret = config('services.telco.webhook_secret');
        $signature = $request->header('x-telco-signature');

        abort_unless(
            $secret && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $signature),
            401,
            'Invalid signature.',
        );

        $payload = $request->json()->all();
        $operatorRef = $payload['operator_ref'] ?? null;
        $result = ($payload['result'] ?? '') === 'success' ? 'success' : 'error';

        // Idempotent: operator_ref keys the event (hash of the body when absent),
        // so a redelivered DLR is a no-op rather than re-advancing the schedule.
        $eventKey = $operatorRef ?? hash('sha256', $request->getContent());
        $event = WebhookEvent::firstOrNew(['source' => 'telco', 'event' => $eventKey]);

        if ($event->exists && $event->processed_at) {
            return response()->json(['status' => 'duplicate']);
        }

        $event->fill(['payload' => $payload, 'status' => 'received'])->save();

        if ($operatorRef && $attempt = TelcoBillingAttempt::where('operator_ref', $operatorRef)->latest()->first()) {
            $attempt->update(['result' => $result]);

            if ($result === 'success') {
                $attempt->telcoSubscription->update(['state' => 'active', 'grace_until' => null, 'next_attempt_at' => now()->addDay()]);
            }
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
