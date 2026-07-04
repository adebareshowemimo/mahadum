<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * Stamps the internal X-Mahadum-* metadata headers on a notification's mail so
 * App\Listeners\RecordSentEmail records the right `source`, `type`, and
 * `user_id` in the email log (§7). Call from `toMail`:
 *
 *     return $this->tagEmail($mail, 'subscription_activated', $notifiable);
 */
trait TagsEmail
{
    protected function tagEmail(
        MailMessage $mail,
        string $source,
        object $notifiable,
        string $type = 'transactional',
    ): MailMessage {
        $userId = $notifiable instanceof User ? (int) $notifiable->getKey() : null;

        return $mail->withSymfonyMessage(function (Email $message) use ($source, $type, $userId) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('X-Mahadum-Source', $source);
            $headers->addTextHeader('X-Mahadum-Type', $type);
            if ($userId !== null) {
                $headers->addTextHeader('X-Mahadum-User-Id', (string) $userId);
            }
        });
    }
}
