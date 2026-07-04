<?php

namespace App\Services\Email;

use App\Mail\CampaignMail;
use App\Models\Contact;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailSuppression;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Resolves a campaign's audience and dispatches the branded, queued send —
 * skipping globally-suppressed addresses (campaigns are always marketing) and
 * recording one recipient row per address. Reused by the admin "Send" action and
 * the scheduled-dispatch command, so a campaign sends the same way either path.
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
        $sent = 0;
        $suppressed = 0;

        foreach ($recipients as $r) {
            if (EmailSuppression::suppresses($r['email'])) {
                $this->record($campaign, $r, 'suppressed');
                $suppressed++;

                continue;
            }

            $this->record($campaign, $r, 'sent');
            $url = URL::signedRoute('email.unsubscribe', ['email' => $r['email']]);
            Mail::to($r['email'])->send(new CampaignMail($campaign->subject, $campaign->body, $url, $campaign->id));
            $sent++;
        }

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'recipients_count' => count($recipients),
            'sent_count' => $sent,
            'failed_count' => 0,
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

    /**
     * @param  array{email: string, user_id: int|null, contact_id: int|null}  $r
     */
    private function record(EmailCampaign $campaign, array $r, string $status): void
    {
        EmailCampaignRecipient::create([
            'email_campaign_id' => $campaign->id,
            'email' => $r['email'],
            'user_id' => $r['user_id'],
            'contact_id' => $r['contact_id'],
            'status' => $status,
        ]);
    }
}
