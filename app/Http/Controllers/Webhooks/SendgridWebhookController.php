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
        $expected = (string) config('services.sendgrid.webhook_token');
        abort_if($expected === '' || ! hash_equals($expected, $token), 403);

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
}
