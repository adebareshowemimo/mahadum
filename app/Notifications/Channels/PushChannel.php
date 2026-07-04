<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Contracts\SendsPush;
use App\Services\Messaging\MessagingManager;
use Illuminate\Notifications\Notification;

class PushChannel
{
    public function __construct(private MessagingManager $messaging) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof SendsPush) {
            return;
        }

        $tokens = $notifiable->devices()
            ->whereNotNull('push_token')
            ->pluck('push_token')
            ->map(fn ($token) => (string) $token)
            ->all();

        if ($tokens === []) {
            return;
        }

        $payload = $notification->toPush($notifiable);

        $this->messaging->gateway()->sendPush(
            $tokens,
            $payload['title'],
            $payload['body'],
            $payload['data'] ?? [],
        );
    }
}
