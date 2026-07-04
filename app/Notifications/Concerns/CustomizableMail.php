<?php

namespace App\Notifications\Concerns;

use App\Models\EmailTemplateOverride;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Lets a super_admin override a template's subject/greeting/body/action in the
 * admin portal (`emails.templates.manage`) without touching code. Call from
 * `toMail()` with the same placeholder tokens documented in
 * config('email_templates.<key>.placeholders'):
 *
 *     $default = (new MailMessage)->subject(...)->line(...);
 *     return $this->applyOverride('wallet_funded', ['{{amount}}' => $amount], $default);
 *
 * With no saved override, $default passes through unchanged — zero behavior
 * change for templates nobody has customized.
 */
trait CustomizableMail
{
    protected function applyOverride(string $key, array $placeholders, MailMessage $default): MailMessage
    {
        $override = EmailTemplateOverride::where('key', $key)->first();

        if (! $override) {
            return $default;
        }

        $sub = fn (?string $text): ?string => $text === null ? null : strtr($text, $placeholders);

        $mail = (new MailMessage)->subject((string) $sub($override->subject));

        if ($override->greeting) {
            $mail->greeting((string) $sub($override->greeting));
        }

        foreach (preg_split('/\n{2,}/', trim($override->body)) as $paragraph) {
            if (trim($paragraph) !== '') {
                $mail->line((string) $sub($paragraph));
            }
        }

        if ($override->action_text && $override->action_url) {
            $mail->action((string) $sub($override->action_text), (string) $sub($override->action_url));
        }

        return $mail;
    }
}
