<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ConfigurationTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'MAHADUM.360 email delivery test');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.configuration-test');
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Mahadum-Source' => 'email_configuration_test',
            'X-Mahadum-Type' => 'transactional',
        ]);
    }
}
