<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a ticket's requester when support replies in the thread
 * (Admin\SupportController@addMessage).
 */
class SupportReply extends Notification implements ShouldQueue
{
    use Queueable, TagsEmail;

    public function __construct(private SupportTicket $ticket, private string $reply) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Re: '.$this->ticket->subject)
            ->greeting('We’ve replied to your request')
            ->line('"'.$this->ticket->subject.'"')
            ->line($this->reply)
            ->action('View the conversation', (string) config('brand.url').'/support')
            ->line('Reply from that page and we’ll pick it back up.');

        return $this->tagEmail($mail, 'support_reply', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'support_reply', 'ticket_id' => $this->ticket->id];
    }
}
