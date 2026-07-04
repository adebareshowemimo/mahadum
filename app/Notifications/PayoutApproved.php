<?php

namespace App\Notifications;

use App\Models\Payout;
use App\Notifications\Concerns\DeliversOverMessagingChannels;
use App\Notifications\Concerns\TagsEmail;
use App\Notifications\Contracts\SendsPush;
use App\Notifications\Contracts\SendsSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a payout beneficiary when their request is approved. Fans out over
 * in-app (database) + email + push + the configured text channel (SMS/WhatsApp).
 */
class PayoutApproved extends Notification implements SendsPush, SendsSms, ShouldQueue
{
    use DeliversOverMessagingChannels, Queueable, TagsEmail;

    public function __construct(private Payout $payout) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payout->amount_minor / 100, 2);

        $mail = (new MailMessage)
            ->subject('Your payout was approved')
            ->line("Your payout of ₦{$amount} has been approved and is being processed.");

        return $this->tagEmail($mail, 'payout_approved', $notifiable);
    }

    public function toSms(object $notifiable): string
    {
        $amount = number_format($this->payout->amount_minor / 100, 2);

        return "Mahadum.360: your payout of ₦{$amount} has been approved.";
    }

    /**
     * @return array{title: string, body: string, data?: array<string, mixed>}
     */
    public function toPush(object $notifiable): array
    {
        $amount = number_format($this->payout->amount_minor / 100, 2);

        return [
            'title' => 'Payout approved',
            'body' => "Your payout of ₦{$amount} has been approved.",
            'data' => ['type' => 'payout_approved', 'payout_id' => (string) $this->payout->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payout_approved',
            'payout_id' => $this->payout->id,
            'amount_minor' => $this->payout->amount_minor,
            'method' => $this->payout->method,
        ];
    }
}
