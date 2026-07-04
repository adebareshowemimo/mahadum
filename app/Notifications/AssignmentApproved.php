<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the learner's own account (when they have a login) after a parent
 * approves their assignment submission and escrowed coins are released
 * (ReviewController@review). COPPA-safe: only reaches an account holder,
 * never a login-less child profile.
 */
class AssignmentApproved extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(
        private AssignmentSubmission $submission,
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
            ->subject('Your assignment was approved 🎉')
            ->greeting('Great job!')
            ->line('Your submitted assignment has been reviewed and approved.')
            ->line("{$this->coinsReleased} coins have been added to your wallet.")
            ->action('View your wallet', config('brand.url').'/wallet');

        $mail = $this->applyOverride('assignment_approved', [
            '{{brand_url}}' => (string) config('brand.url'),
            '{{coins}}' => (string) $this->coinsReleased,
        ], $default);

        return $this->tagEmail($mail, 'assignment_approved', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assignment_approved',
            'submission_id' => $this->submission->id,
            'coins_released' => $this->coinsReleased,
        ];
    }
}
