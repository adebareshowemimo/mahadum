<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\File;

/**
 * Render a sample email to HTML using the one global brand template, so design
 * can be iterated without sending. Writes to storage/app/mail-previews/.
 */
class PreviewEmail extends Command
{
    protected $signature = 'mail:preview {--out= : Output HTML path (defaults to storage/app/mail-previews/sample.html)}';

    protected $description = 'Render a sample branded email to HTML for design review';

    public function handle(Markdown $markdown): int
    {
        $message = (new MailMessage)
            ->subject('Welcome to '.config('brand.name'))
            ->greeting('Ẹ ku àbọ̀ — welcome!')
            ->line('Your '.config('brand.name').' account is ready. '.config('brand.tagline'))
            ->action('Start learning', config('brand.url'))
            ->line('Set up a learner profile and pick a language to begin.')
            ->line('If you didn’t create this account, you can ignore this email.');

        $html = (string) $markdown->render('notifications::email', $message->data());

        $out = $this->option('out') ?: storage_path('app/mail-previews/sample.html');
        File::ensureDirectoryExists(dirname($out));
        File::put($out, $html);

        $this->info('Rendered sample email → '.$out);

        return self::SUCCESS;
    }
}
