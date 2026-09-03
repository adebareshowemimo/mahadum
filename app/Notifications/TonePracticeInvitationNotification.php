<?php

namespace App\Notifications;

use App\Models\TonePracticeInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TonePracticeInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private TonePracticeInvitation $invitation,
        private string $plainToken,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/tone-practice/'.$this->plainToken;
        $inviter = $this->invitation->inviter->name;

        return (new MailMessage)
            ->subject('A Mahadum.360 tone-practice invitation')
            ->greeting('Hello')
            ->line("{$inviter} invited you to help with a short language tone-practice activity.")
            ->line('For learner privacy, sign in with this email address to open it. The learner’s contact details are never shared.')
            ->action('Open tone practice', $url)
            ->line('This invitation expires in 48 hours.');
    }
}
