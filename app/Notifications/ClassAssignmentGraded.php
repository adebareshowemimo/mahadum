<?php

namespace App\Notifications;

use App\Models\ClassAssignmentSubmission;
use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the learner's own account (when they have a login) after their
 * teacher grades a class assignment submission
 * (ClassAssignmentController@grade). Coins release only when passed.
 */
class ClassAssignmentGraded extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(
        private ClassAssignmentSubmission $submission,
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
        $title = $this->submission->classAssignment->title;
        $passed = $this->submission->passed === true;

        $default = (new MailMessage)
            ->subject($passed ? 'Your assignment was graded 🎉' : 'Your assignment was graded')
            ->greeting($passed ? 'Great job!' : 'Assignment graded')
            ->line("Your teacher graded \"{$title}\".")
            ->line($passed
                ? "{$this->coinsReleased} coins have been added to your wallet."
                : 'Check the feedback from your teacher and try again.')
            ->action('View assignment', config('brand.url').'/assignments');

        $mail = $this->applyOverride('class_assignment_graded', [
            '{{brand_url}}' => (string) config('brand.url'),
            '{{assignment_title}}' => (string) $title,
            '{{coins}}' => (string) $this->coinsReleased,
        ], $default);

        return $this->tagEmail($mail, 'class_assignment_graded', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'class_assignment_graded',
            'submission_id' => $this->submission->id,
            'passed' => $this->submission->passed,
            'coins_released' => $this->coinsReleased,
        ];
    }
}
