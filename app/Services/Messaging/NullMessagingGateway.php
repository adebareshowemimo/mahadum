<?php

namespace App\Services\Messaging;

/** No-op transports for local/CI — nothing is sent. */
class NullMessagingGateway implements MessagingGateway
{
    public function sendSms(string $to, string $message): void
    {
        // no-op
    }

    public function sendWhatsapp(string $to, string $message): void
    {
        // no-op
    }

    public function sendPush(array $tokens, string $title, string $body, array $data = []): void
    {
        // no-op
    }
}
