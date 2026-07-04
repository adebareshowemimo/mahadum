<?php

namespace App\Services\Email;

use App\Jobs\SendCampaignEmail;
use App\Models\Contact;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailSuppression;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * Resolves a campaign's audience and dispatches a batched, branded send —
 * skipping globally-suppressed addresses (campaigns are always marketing) and
 * recording one recipient row per address. Each email is a queued batch job that
 * records its real outcome, so counts are exact and a mid-send crash is resumable.
 * Reused by the admin "Send" action and the scheduled-dispatch command.
 */
class CampaignSender
{
    public function send(EmailCampaign $campaign): void
    {
        // Guard against a double-send (scheduler + manual, or a re-run).
        if (in_array($campaign->status, ['sending', 'sent'], true)) {
            return;
        }

        $campaign->update(['status' => 'sending']);

        $recipients = $this->resolve($campaign);
        $queuedIds = [];

        foreach ($recipients as $r) {
            $status = EmailSuppression::suppresses($r['email']) ? 'suppressed' : 'queued';
            $row = EmailCampaignRecipient::create([
                'email_campaign_id' => $campaign->id,
                'email' => $r['email'],
                'user_id' => $r['user_id'],
                'contact_id' => $r['contact_id'],
                'status' => $status,
            ]);
            if ($status === 'queued') {
                $queuedIds[] = $row->id;
            }
        }

        $campaign->update(['recipients_count' => count($recipients)]);

        // Nothing to send (all suppressed / empty audience) → finalise now.
        if ($queuedIds === []) {
            $this->finalise($campaign->id);

            return;
        }

        $campaignId = $campaign->id;
        Bus::batch(array_map(fn (int $id) => new SendCampaignEmail($id), $queuedIds))
            ->name("email-campaign:{$campaignId}")
            ->finally(fn (Batch $batch) => (new self)->finalise($campaignId))
            ->dispatch();
    }

    /**
     * Recompute counts from the recorded per-recipient outcomes and mark the
     * campaign sent. Public so the batch's `finally` closure can call it.
     */
    public function finalise(int $campaignId): void
    {
        $campaign = EmailCampaign::find($campaignId);
        if (! $campaign) {
            return;
        }

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $campaign->recipients()->where('status', 'sent')->count(),
            'failed_count' => $campaign->recipients()->where('status', 'failed')->count(),
        ]);
    }

    /**
     * Resolve the recipient set to a list of [email, user_id, contact_id].
     *
     * @return array<int, array{email: string, user_id: int|null, contact_id: int|null}>
     */
    public function resolve(EmailCampaign $campaign): array
    {
        if ($campaign->audience_type === 'contact_list') {
            $listId = $campaign->audience['contact_list_id'] ?? 0;

            return Contact::where('contact_list_id', $listId)
                ->where('status', 'subscribed')
                ->get(['id', 'email'])
                ->map(fn (Contact $c) => ['email' => $c->email, 'user_id' => null, 'contact_id' => $c->id])
                ->all();
        }

        $filters = $campaign->audience ?? [];
        $query = User::query()->whereNotNull('email');

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $filters['role']));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['organization_id'])) {
            $query->whereHas('organizations', fn ($o) => $o->where('organizations.id', $filters['organization_id']));
        }

        return $query->get(['id', 'email'])
            ->map(fn (User $u) => ['email' => $u->email, 'user_id' => $u->id, 'contact_id' => null])
            ->all();
    }
}
