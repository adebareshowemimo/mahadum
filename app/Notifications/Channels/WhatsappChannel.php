<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Contracts\SendsSms;
use App\Services\Messaging\MessagingManager;
use Illuminate\Notifications\Notification;

class WhatsappChannel
{
    public function __construct(private MessagingManager $messaging) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notifiable->phone || ! $notification instanceof SendsSms) {
            return;
        }

        $this->messaging->gateway()->sendWhatsapp($notifiable->phone, $notification->toSms($notifiable));
    }
}
