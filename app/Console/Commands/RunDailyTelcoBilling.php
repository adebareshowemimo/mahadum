<?php

namespace App\Console\Commands;

use App\Models\TelcoBillingAttempt;
use App\Models\TelcoSubscription;
use App\Services\Billing\TelcoGatewayManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Daily airtime (VAS) billing. For each due subscription we attempt a charge via
 * the operator SDP (TelcoGatewayManager → NullTelcoGateway when off-live);
 * success advances the next attempt, failure drops to a 48h grace window with a
 * 24h retry (active → grace → soft_downgrade).
 */
class RunDailyTelcoBilling extends Command
{
    protected $signature = 'telco:bill-daily';

    protected $description = 'Charge airtime for due telco subscriptions';

    public function handle(TelcoGatewayManager $gateways): int
    {
        $gateway = $gateways->driver();

        $due = TelcoSubscription::whereIn('state', ['active', 'grace'])
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->get();

        foreach ($due as $telco) {
            $reference = (string) Str::uuid();
            $result = $gateway->charge($telco->msisdn, $telco->operator, $telco->daily_amount_minor, $reference);

            TelcoBillingAttempt::create([
                'telco_subscription_id' => $telco->id,
                'attempted_at' => now(),
                'amount_minor' => $telco->daily_amount_minor,
                'result' => $result->status,
                'operator_ref' => $result->operatorRef ?? $reference,
            ]);

            if ($result->status === 'success') {
                $telco->update(['state' => 'active', 'grace_until' => null, 'next_attempt_at' => now()->addDay()->setTime(2, 0)]);
            } elseif ($telco->state === 'active') {
                $telco->update(['state' => 'grace', 'grace_until' => now()->addHours(48), 'next_attempt_at' => now()->addDay()]);
            } else {
                $telco->update(['next_attempt_at' => now()->addDay()]); // stay in grace, retry tomorrow
            }
        }

        $this->info("Processed {$due->count()} telco subscription(s).");

        return self::SUCCESS;
    }
}
