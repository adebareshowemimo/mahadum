<?php

namespace App\Mail;

use App\Services\EmailHtmlSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A campaign blast to one recipient. Renders the admin-authored Markdown or
 * sanitized rich-HTML body in the brand template, tags itself as marketing from campaign:{id} (so the
 * email log + suppression treat it correctly), and carries a per-recipient
 * unsubscribe link. Sent synchronously by the SendCampaignEmail batch job (which
 * owns the queueing), so it is intentionally not ShouldQueue.
 */
class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $unsubscribeUrl,
        public int $campaignId,
        public string $contentMode = 'markdown',
        public ?string $htmlBody = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        if ($this->contentMode === 'html' && filled($this->htmlBody)) {
            return new Content(view: 'emails.custom-html', with: [
                'html' => app(EmailHtmlSanitizer::class)->sanitize($this->htmlBody),
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ]);
        }

        return new Content(markdown: 'emails.campaign', with: [
            'bodyHtml' => Str::markdown($this->body),
            'unsubscribeUrl' => $this->unsubscribeUrl,
        ]);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Mahadum-Source' => 'campaign:'.$this->campaignId,
            'X-Mahadum-Type' => 'marketing',
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
        ]);
    }
}
