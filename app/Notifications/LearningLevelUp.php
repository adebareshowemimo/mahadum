<?php

namespace App\Notifications;

use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a learner reaches a new learning level and earns that tier's badge
 * (BadgeService). Goes to the learner's own account when they have a login,
 * otherwise to the family owner (COPPA-safe — never a login-less child).
 */
class LearningLevelUp extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(
        private string $learnerName,
        private int $level,
        private string $badgeName,
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
