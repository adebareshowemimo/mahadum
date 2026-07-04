<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A campaign blast to one recipient. Renders the admin-authored markdown body in
 * the one brand template, tags itself as marketing from campaign:{id} (so the
 * email log + suppression treat it correctly), and carries a per-recipient
 * unsubscribe link. Queued so a large blast doesn't block the request.
 */
class CampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $unsubscribeUrl,
        public int $campaignId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
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
