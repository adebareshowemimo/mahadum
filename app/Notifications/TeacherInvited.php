<?php

namespace App\Notifications;

use App\Models\TeacherInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeacherInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TeacherInvitation $invitation, private string $plainToken) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your teaching invitation to '.$this->invitation->organization->name)
            ->greeting('Hello '.$this->invitation->name)
            ->line('A school administrator has invited you to teach at '.$this->invitation->organization->name.'.')
            ->line('Sign in or create an account with this email address, verify your email, then accept the invitation. Your existing family profiles are kept.')
            ->action('Review teaching invitation', rtrim((string) config('app.frontend_url'), '/').'/teacher-invitations/'.$this->plainToken)
            ->line('This invitation expires in seven days.');
    }
}
