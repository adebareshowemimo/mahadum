<?php

namespace App\Notifications;

use App\Models\CoursePracticeInvitation;
use App\Notifications\Concerns\CustomizableMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoursePracticeInvitationNotification extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable;

    public function __construct(
        private CoursePracticeInvitation $invitation,
        private string $plainToken,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/course-practice/'.$this->plainToken;
        $inviter = $this->invitation->inviter->name;
        $lessonTitle = $this->invitation->lesson->title;

        $default = (new MailMessage)
            ->subject("{$inviter} invited you to practice on Mahadum.360")
            ->greeting('Hello')
            ->line("{$inviter} invited you to practice “{$lessonTitle}” together.")
            ->line('For learner privacy, sign in with this email address to open it. The learner’s contact details are never shared.')
            ->action('Practice together', $url)
            ->line('This invitation expires in 48 hours.');

        return $this->applyOverride('course_practice_invitation', [
            '{{inviter}}' => $inviter,
            '{{lesson_title}}' => $lessonTitle,
            '{{url}}' => $url,
        ], $default);
    }
}
