<?php

namespace App\Services\Messaging;

/**
 * Resolves the outbound messaging gateway. Returns a NullMessagingGateway (no
 * HTTP) unless `services.messaging.live` is on, so the live transports are
 * opt-in per environment.
 */
class MessagingManager
{
    public function gateway(): MessagingGateway
    {
        if (! config('services.messaging.live')) {
            return new NullMessagingGateway;
        }

        return new HttpMessagingGateway(
            config('services.messaging.sms.base_url'),
            config('services.messaging.sms.token'),
            (string) config('services.messaging.sms.sender', 'Mahadum360'),
            config('services.messaging.whatsapp.base_url'),
            config('services.messaging.whatsapp.token'),
            (string) config('services.messaging.push.fcm_url', 'https://fcm.googleapis.com/fcm/send'),
            config('services.messaging.push.key'),
        );
    }
}
