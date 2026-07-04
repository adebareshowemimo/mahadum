<?php

namespace App\Notifications;

use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security alert — sent when an account is signed into from a device we haven't
 * seen for that user before. Transactional (never suppressed).
 */
class NewDeviceAlert extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(private ?string $ip, private ?string $userAgent) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $default = (new MailMessage)
            ->subject('New sign-in to your '.config('brand.name').' account')
            ->greeting('New sign-in detected')
            ->line('Your account was just signed into from a device we haven’t seen before.')
            ->line('IP address: '.($this->ip ?: 'unknown'))
            ->line('Device: '.($this->userAgent ?: 'unknown'))
            ->line('If this was you, no action is needed.')
            ->line('If it wasn’t, reset your password right away.')
            ->action('Reset your password', config('brand.url').'/forgot-password');

        $mail = $this->applyOverride('login_alert', [
            '{{brand_name}}' => (string) config('brand.name'),
            '{{brand_url}}' => (string) config('brand.url'),
            '{{ip}}' => $this->ip ?: 'unknown',
            '{{device}}' => $this->userAgent ?: 'unknown',
        ], $default);

        return $this->tagEmail($mail, 'login_alert', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'login_alert', 'ip' => $this->ip];
    }
}
