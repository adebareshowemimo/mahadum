<?php

namespace App\Notifications;

use App\Models\ClassLearnerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassLearnerInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ClassLearnerInvitation $invitation,
        private string $plainToken,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/class-invitations/'.$this->plainToken;

        return (new MailMessage)
            ->subject("You're invited to {$this->invitation->schoolClass->name} on ".config('brand.name'))
            ->greeting("Hello {$this->invitation->name}")
            ->line("You've been invited to join **{$this->invitation->schoolClass->name}** at **{$this->invitation->organization->name}**.")
            ->line('If you already have an account, sign in. If you are new, register with this invited email address.')
            ->action('Join class', $url)
            ->line('This invitation expires in 7 days.');
    }
}
