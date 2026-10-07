<?php

namespace App\Notifications;

use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\DeliversOverMessagingChannels;
use App\Notifications\Concerns\TagsEmail;
use App\Notifications\Contracts\SendsPush;
use App\Notifications\Contracts\SendsSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a learner reaches a new learning level and earns that tier's badge
 * (BadgeService). Goes to the learner's own account when they have a login,
 * otherwise to the family owner (COPPA-safe — never a login-less child).
 */
class LearningLevelUp extends Notification implements SendsPush, SendsSms, ShouldQueue
{
    use CustomizableMail, DeliversOverMessagingChannels, Queueable, TagsEmail;

    public function __construct(
        private string $learnerName,
        private int $level,
        private string $badgeName,
    ) {}

    public function toSms(object $notifiable): string
    {
        return "{$this->learnerName} has earned the {$this->badgeName} badge. See achievements in your account.";
    }

    public function toPush(object $notifiable): array
    {
        return ['title' => 'New learning achievement', 'body' => $this->toSms($notifiable), 'data' => ['url' => '/achievements']];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $default = (new MailMessage)
            ->subject("Level {$this->level} unlocked — {$this->badgeName} 🎉")
            ->greeting('Congratulations!')
            ->line("{$this->learnerName} has completed Level {$this->level} and earned the {$this->badgeName} badge.")
            ->action('See achievements', config('brand.url').'/achievements');

        $mail = $this->applyOverride('learning_level_up', [
            '{{brand_url}}' => (string) config('brand.url'),
            '{{learner_name}}' => $this->learnerName,
            '{{level}}' => (string) $this->level,
            '{{badge_name}}' => $this->badgeName,
        ], $default);

        return $this->tagEmail($mail, 'learning_level_up', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'learning_level_up',
            'level' => $this->level,
            'badge_name' => $this->badgeName,
            'learner_name' => $this->learnerName,
            'message' => "Congratulations! {$this->learnerName} has completed Level {$this->level} and earned the {$this->badgeName} badge.",
        ];
    }
}
