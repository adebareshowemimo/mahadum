<?php

namespace App\Notifications;

use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Wallet top-up receipt — sent when a card/gateway funding is confirmed and the
 * balance is credited (PaymentService@settleFunding).
 */
class WalletFunded extends Notification implements ShouldQueue
{
    use Queueable, TagsEmail;

    public function __construct(private int $amountMinor) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->amountMinor / 100, 2);

        $mail = (new MailMessage)
            ->subject('Your '.config('brand.name').' wallet has been topped up')
            ->greeting('Payment received')
            ->line("₦{$amount} has been added to your wallet.")
            ->line('This message is your receipt. You can spend it on subscriptions and family features.')
            ->action('Open your wallet', config('brand.url').'/wallet');

        return $this->tagEmail($mail, 'wallet_funded', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'wallet_funded', 'amount_minor' => $this->amountMinor];
    }
}
