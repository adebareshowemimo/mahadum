<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dunning — sent when a charge for a subscription fails, so the payer can retry
 * before access lapses. Transactional (never suppressed).
 */
class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable, TagsEmail;

    public function __construct(private Subscription $subscription) {}

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
            ->subject('Your '.config('brand.name').' payment didn’t go through')
            ->greeting('Payment problem')
            ->line("We couldn’t process the payment for your {$this->subscription->plan->name} subscription.")
            ->line('Please update your payment method to keep your access without a lapse.')
            ->action('Retry payment', (string) config('brand.url').'/billing');

        return $this->tagEmail($mail, 'payment_failed', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'payment_failed', 'subscription_id' => $this->subscription->id];
    }
}
