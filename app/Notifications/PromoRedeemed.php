<?php

namespace App\Notifications;

use App\Models\PromoCode;
use App\Models\Subscription;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation that a promo code was applied to a subscription
 * (PromoService@redeem, at consumer checkout).
 */
class PromoRedeemed extends Notification implements ShouldQueue
{
    use Queueable, TagsEmail;

    public function __construct(private PromoCode $promo, private Subscription $subscription) {}

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
            ->subject('Your promo code was applied')
            ->greeting('Discount applied 🎉')
            ->line("Promo code **{$this->promo->code}** was applied to your {$this->subscription->plan->name} subscription.")
            ->line('This message is your confirmation.')
            ->action('View your billing', (string) config('brand.url').'/billing');

        return $this->tagEmail($mail, 'promo_redeemed', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'promo_redeemed', 'code' => $this->promo->code];
    }
}
