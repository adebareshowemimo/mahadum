<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\DeliversOverMessagingChannels;
use App\Notifications\Concerns\TagsEmail;
use App\Notifications\Contracts\SendsPush;
use App\Notifications\Contracts\SendsSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Upcoming-renewal reminder — sent a few days before a card/invoice subscription
 * is due to renew, so the subscriber can keep access without a lapse. Telco
 * (airtime) subscriptions auto-bill daily and are excluded by the command.
 */
class SubscriptionRenewalReminder extends Notification implements SendsPush, SendsSms, ShouldQueue
{
    use DeliversOverMessagingChannels, Queueable, TagsEmail;

    public function __construct(private Subscription $subscription) {}

    private function renewsOn(): string
    {
        return $this->subscription->renews_at?->format('j M Y') ?? 'soon';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $amount = number_format(($plan->price_minor ?? 0) / 100, 2);

        $mail = (new MailMessage)
            ->subject('Your Mahadum.360 plan renews soon')
            ->greeting('Hi there,')
            ->line("Your {$plan->name} plan renews on {$this->renewsOn()}.")
            ->line("Amount: ₦{$amount}")
            ->line('Make sure your payment method is ready so your access continues uninterrupted.');

        return $this->tagEmail($mail, 'subscription_renewal_reminder', $notifiable);
    }

    public function toSms(object $notifiable): string
    {
        return "Mahadum.360: your {$this->subscription->plan->name} plan renews on {$this->renewsOn()}. Keep your payment ready to avoid a lapse.";
    }

    /**
     * @return array{title: string, body: string, data?: array<string, mixed>}
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Renewal coming up',
            'body' => "Your {$this->subscription->plan->name} plan renews on {$this->renewsOn()}.",
            'data' => ['type' => 'subscription_renewal_reminder', 'subscription_id' => (string) $this->subscription->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_renewal_reminder',
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->plan->name,
            'renews_at' => $this->subscription->renews_at?->toIso8601String(),
        ];
    }
}
