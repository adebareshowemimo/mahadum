<?php

namespace App\Notifications;

use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome email — sent once a user verifies their email (the "you're in" moment).
 * COPPA-safe by construction: a learner under 13 has no login/email, so this only
 * ever reaches the account holder (adult / parent).
 */
class WelcomeEmail extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

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
            ->subject('Welcome to '.config('brand.name'))
            ->greeting('Ẹ ku àbọ̀ — welcome!')
            ->line('Your account is verified and ready. '.config('brand.tagline'))
            ->action('Start learning', (string) config('brand.url'))
            ->line('Set up a learner profile and pick a language to begin.');

        $mail = $this->applyOverride('welcome', [
            '{{brand_name}}' => (string) config('brand.name'),
            '{{brand_tagline}}' => (string) config('brand.tagline'),
            '{{brand_url}}' => (string) config('brand.url'),
        ], $default);

        return $this->tagEmail($mail, 'welcome', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'welcome'];
    }
}
