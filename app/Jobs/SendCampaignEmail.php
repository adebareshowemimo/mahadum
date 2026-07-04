<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\EmailCampaignRecipient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Sends one campaign email and records the real per-recipient outcome. Part of a
 * Bus batch, so a large blast is chunked across the queue and a mid-send crash is
 * resumable — a recipient already `sent` is skipped on re-run (idempotent).
 */
class SendCampaignEmail implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $recipientId) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $recipient = EmailCampaignRecipient::with('campaign')->find($this->recipientId);
        // Only send a still-queued recipient (idempotent resume; skips already-sent).
        if (! $recipient || $recipient->status !== 'queued' || ! $recipient->campaign) {
            return;
        }

        $campaign = $recipient->campaign;

        try {
            $url = URL::signedRoute('email.unsubscribe', ['email' => $recipient->email]);
            Mail::to($recipient->email)->send(new CampaignMail($campaign->subject, $campaign->body, $url, $campaign->id));
            $recipient->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            $recipient->update(['status' => 'failed']);
        }
    }
}
