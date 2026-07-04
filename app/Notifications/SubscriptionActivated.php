<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\DeliversOverMessagingChannels;
use App\Notifications\Contracts\SendsPush;
use App\Notifications\Contracts\SendsSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment receipt — sent to the subscriber when their subscription is confirmed
 * active by a gateway webhook. Fans out over in-app (database) + email + push +
 * the configured text channel (SMS/WhatsApp).
 */
class SubscriptionActivated extends Notification implements SendsPush, SendsSms, ShouldQueue
{
    use DeliversOverMessagingChannels, Queueable;

    public function __construct(private Subscription $subscription) {}

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $amount = number_format(($plan->price_minor ?? 0) / 100, 2);

        return (new MailMessage)
            ->subject('Your Mahadum.360 subscription is active')
            ->greeting('Thank you!')
            ->line("Your {$plan->name} plan is now active.")
            ->line("Amount: ₦{$amount}")
            ->line('This message is your receipt.');
    }

    public function toSms(object $notifiable): string
    {
        $amount = number_format(($this->subscription->plan->price_minor ?? 0) / 100, 2);

        return "Mahadum.360: your {$this->subscription->plan->name} plan is active. Amount ₦{$amount}.";
    }

    /**
     * @return array{title: string, body: string, data?: array<string, mixed>}
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Subscription active',
            'body' => "Your {$this->subscription->plan->name} plan is now active.",
            'data' => ['type' => 'subscription_activated', 'subscription_id' => (string) $this->subscription->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_activated',
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->plan->name,
            'amount_minor' => (int) $this->subscription->plan->price_minor,
        ];
    }
}
