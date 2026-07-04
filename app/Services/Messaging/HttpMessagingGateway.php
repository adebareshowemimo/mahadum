<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;

/**
 * HTTP transports for the live messaging providers (generic SMS/WhatsApp gateway
 * + FCM push). All sends are best-effort — a provider hiccup never throws into
 * the notification pipeline; each send no-ops if its provider isn't configured.
 * Field shapes are illustrative; adjust to the contracted provider APIs.
 */
class HttpMessagingGateway implements MessagingGateway
{
    public function __construct(
        private ?string $smsUrl,
        private ?string $smsToken,
        private string $smsSender,
        private ?string $whatsappUrl,
        private ?string $whatsappToken,
        private string $fcmUrl,
        private ?string $fcmKey,
    ) {}

    public function sendSms(string $to, string $message): void
    {
        if (! $this->smsUrl) {
            return;
        }

        Http::withToken((string) $this->smsToken)
            ->acceptJson()
            ->post(rtrim($this->smsUrl, '/').'/send', [
                'to' => $to,
                'sender' => $this->smsSender,
                'message' => $message,
            ]);
    }

    public function sendWhatsapp(string $to, string $message): void
    {
        if (! $this->whatsappUrl) {
            return;
        }

        Http::withToken((string) $this->whatsappToken)
            ->acceptJson()
            ->post(rtrim($this->whatsappUrl, '/').'/messages', [
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);
    }

    public function sendPush(array $tokens, string $title, string $body, array $data = []): void
    {
        if (! $this->fcmKey || $tokens === []) {
            return;
        }

        Http::withToken($this->fcmKey)
            ->acceptJson()
            ->post($this->fcmUrl, [
                'registration_ids' => array_values($tokens),
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $data,
            ]);
    }
}
