<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsappChannel;

/**
 * Standard fan-out for user-facing notifications: in-app (database) + email +
 * push, plus one text channel chosen by `services.messaging.text_channel`
 * (sms | whatsapp | none) so the same notification never double-texts.
 */
trait DeliversOverMessagingChannels
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail', PushChannel::class];

        return match ((string) config('services.messaging.text_channel', 'sms')) {
            'whatsapp' => [...$channels, WhatsappChannel::class],
            'none' => $channels,
            default => [...$channels, SmsChannel::class],
        };
    }
}
