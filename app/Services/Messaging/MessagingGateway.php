<?php

namespace App\Services\Messaging;

/**
 * Outbound message transports (SMS, WhatsApp, push). One swappable surface
 * resolved by MessagingManager; notification channels call into it. Off-live the
 * NullMessagingGateway no-ops, so local/CI never reach a real provider.
 */
interface MessagingGateway
{
    public function sendSms(string $to, string $message): void;

    public function sendWhatsapp(string $to, string $message): void;

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, mixed>  $data
     */
    public function sendPush(array $tokens, string $title, string $body, array $data = []): void;
}
