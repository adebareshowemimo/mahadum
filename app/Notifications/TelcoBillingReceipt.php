<?php

namespace App\Notifications;

use App\Models\TelcoSubscription;
use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Airtime (VAS/SDP) daily-billing receipt — sent to the subscriber when
 * RunDailyTelcoBilling successfully charges their operator balance.
 */
class TelcoBillingReceipt extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(private TelcoSubscription $telco) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->telco->daily_amount_minor / 100, 2);
        $plan = $this->telco->subscription->plan->name ?? 'subscription';

        $default = (new MailMessage)
            ->subject('Your airtime billing receipt')
            ->greeting('Payment received')
            ->line("₦{$amount} was charged to your {$this->telco->operator} airtime balance for your {$plan} plan.")
            ->line('This message is your receipt.');

        $mail = $this->applyOverride('telco_billing_receipt', [
            '{{amount}}' => $amount,
            '{{operator}}' => (string) $this->telco->operator,
            '{{plan_name}}' => (string) $plan,
        ], $default);

        return $this->tagEmail($mail, 'telco_billing_receipt', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'telco_billing_receipt',
            'telco_subscription_id' => $this->telco->id,
            'amount_minor' => $this->telco->daily_amount_minor,
        ];
    }
}
