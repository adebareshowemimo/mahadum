<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionRenewalReminder;
use Illuminate\Console\Command;

/**
 * Notifies subscribers whose card/invoice plan renews within the reminder window,
 * so they can keep their payment method ready and avoid a lapse. Telco (airtime)
 * subscriptions auto-bill daily and are skipped. `renewal_reminded_at` guards
 * against re-sending within the same billing cycle.
 */
class SendRenewalReminders extends Command
{
    protected $signature = 'subscriptions:remind {--days=3 : Remind when a plan renews within this many days}';

    protected $description = 'Send upcoming-renewal reminders for card/invoice subscriptions';

    public function handle(): int
    {
        $window = max(1, (int) $this->option('days'));
        $now = now();

        $due = Subscription::with('plan')
            ->where('status', 'active')
            ->where('method', '!=', 'airtime') // telco/airtime auto-bills daily; no reminder needed
            ->whereNotNull('renews_at')
            ->whereBetween('renews_at', [$now, $now->copy()->addDays($window)])
            ->get();

        $sent = 0;
        foreach ($due as $sub) {
            // Skip if already reminded for THIS cycle (renews_at advances on renewal,
            // so a prior cycle's reminder falls before this cutoff and re-qualifies).
            $cutoff = $sub->renews_at->copy()->subDays($window + 1);
            if ($sub->renewal_reminded_at && $sub->renewal_reminded_at->greaterThan($cutoff)) {
                continue;
            }

            $notifiable = $this->notifiableFor($sub);
            if ($notifiable === null) {
                continue;
            }

            $notifiable->notify(new SubscriptionRenewalReminder($sub));
            $sub->update(['renewal_reminded_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} renewal reminder(s).");

        return self::SUCCESS;
    }

    /** Resolve who to notify: the subscriber user, or a family's owner. */
    private function notifiableFor(Subscription $sub): ?User
    {
        $subscriber = $sub->subscriber;

        return match (true) {
            $subscriber instanceof User => $subscriber,
            $subscriber instanceof Family => $subscriber->ownerUser,
            default => null,
        };
    }
}
