<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailSuppression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SendGrid Event Webhook. Guarded by a shared-secret token in the URL. Ingests
 * delivery events: hard bounces / drops / spam reports add the address to the
 * global suppression list (so it's never marketed again), and every event
 * updates the recipient's most recent email-log row. Idempotent.
 */
class SendgridWebhookController extends Controller
{
    /** SendGrid event → the suppression reason it implies (null = no suppression). */
    private const SUPPRESS = [
        'bounce' => 'bounce',
        'dropped' => 'bounce',
        'spamreport' => 'complaint',
    ];

    /** SendGrid event → the email-log status it maps to. */
    private const LOG_STATUS = [
        'delivered' => 'delivered',
        'bounce' => 'bounced',
        'dropped' => 'bounced',
        'spamreport' => 'complained',
    ];

    public function handle(Request $request, string $token): JsonResponse
    {
        abort_unless($this->verified($request, $token), 403);

        /** @var array<int, array<string, mixed>> $events */
        $events = $request->json()->all();

        foreach ($events as $event) {
            $email = mb_strtolower(trim((string) ($event['email'] ?? '')));
            $type = (string) ($event['event'] ?? '');
            if ($email === '' || $type === '') {
                continue;
            }

            if ($reason = self::SUPPRESS[$type] ?? null) {
                EmailSuppression::firstOrCreate(['email' => $email], ['reason' => $reason]);
            }

            if ($status = self::LOG_STATUS[$type] ?? null) {
                EmailLog::where('to_email', $email)->latest()->first()?->update(['status' => $status]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Prefer SendGrid's ECDSA Signed Event Webhook when a public key is
     * configured; otherwise fall back to the shared-secret URL token.
     */
    private function verified(Request $request, string $token): bool
    {
        $publicKey = (string) config('services.sendgrid.webhook_public_key');
        if ($publicKey !== '') {
            return $this->verifySignature($request, $publicKey);
        }

        $expected = (string) config('services.sendgrid.webhook_token');

        return $expected !== '' && hash_equals($expected, $token);
    }

    private function verifySignature(Request $request, string $base64PublicKey): bool
    {
        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');
        if (! $signature || ! $timestamp) {
            return false;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split($base64PublicKey, 64, "\n").'-----END PUBLIC KEY-----';
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            return false;
        }

        // SendGrid signs (timestamp + raw request body).
        $ok = openssl_verify($timestamp.$request->getContent(), base64_decode($signature), $key, OPENSSL_ALGO_SHA256);

        return $ok === 1;
    }
}
