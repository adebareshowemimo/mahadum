<?php

namespace App\Notifications;

use App\Notifications\Concerns\DeliversOverMessagingChannels;
use App\Notifications\Contracts\SendsPush;
use App\Notifications\Contracts\SendsSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyActivityAlert extends Notification implements SendsPush, SendsSms, ShouldQueue
{
    use DeliversOverMessagingChannels, Queueable;

    public function __construct(public string $kind, public string $title, public string $message, public string $url) {}

    public function toArray(object $notifiable): array
    {
        return ['type' => $this->kind, 'title' => $this->title, 'message' => $this->message, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->line($this->message)->action('Open your account', rtrim((string) config('app.frontend_url'), '/').$this->url);
    }

    public function toSms(object $notifiable): string
    {
        return $this->title.'. '.$this->message;
    }

    public function toPush(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->message, 'data' => ['url' => $this->url]];
    }
}
