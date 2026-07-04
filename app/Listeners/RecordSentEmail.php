<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

/**
 * Central email capture: writes one EmailLog row per recipient for every outbound
 * message, so the admin email log (§7) records all mail with no per-email code.
 * `source` / `type` / `user_id` are read from optional X-Mahadum-* metadata
 * headers a Mailable or Notification may set (see App\Mail\Concerns\TagsEmail);
 * sensible defaults otherwise. Runs on MessageSent, so only actually-sent mail is
 * recorded; bounce/complaint webhooks later update the row by message_id.
 */
class RecordSentEmail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message; // Symfony\Component\Mime\Email (original message)

        $source = $this->header($message, 'X-Mahadum-Source');
        $type = $this->header($message, 'X-Mahadum-Type') ?? 'transactional';
        $userId = ($id = $this->header($message, 'X-Mahadum-User-Id')) !== null ? (int) $id : null;

        $subject = $message->getSubject();
        $messageId = $event->sent->getMessageId();
        $now = now();

        foreach ($message->getTo() as $address) {
            EmailLog::create([
                'to_email' => $address->getAddress(),
                'user_id' => $userId,
                'type' => $type,
                'source' => $source,
                'subject' => $subject,
                'status' => 'sent',
                'message_id' => $messageId,
                'sent_at' => $now,
            ]);
        }
    }

    private function header(Email $message, string $name): ?string
    {
        $header = $message->getHeaders()->get($name);

        return $header?->getBodyAsString() ?: null;
    }
}
