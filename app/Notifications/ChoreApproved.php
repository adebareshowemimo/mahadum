<?php

namespace App\Notifications;

use App\Models\Chore;
use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the learner's own account (when they have a login) after a parent
 * approves their chore and coins are released to their wallet
 * (ChoreController@review). COPPA-safe: only reaches an account holder, never
 * a login-less child profile.
 */
class ChoreApproved extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(
        private Chore $chore,
        private int $coinsReleased,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $default = (new MailMessage)
            ->subject('Your chore was approved 🎉')
            ->greeting('Great job!')
            ->line("Your chore \"{$this->chore->title}\" was approved.")
            ->line("{$this->coinsReleased} coins have been added to your wallet.")
            ->action('View your wallet', config('brand.url').'/wallet');

        $mail = $this->applyOverride('chore_approved', [
            '{{brand_url}}' => (string) config('brand.url'),
            '{{chore_title}}' => (string) $this->chore->title,
            '{{coins}}' => (string) $this->coinsReleased,
        ], $default);

        return $this->tagEmail($mail, 'chore_approved', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chore_approved',
            'chore_id' => $this->chore->id,
            'coins_released' => $this->coinsReleased,
        ];
    }
}
