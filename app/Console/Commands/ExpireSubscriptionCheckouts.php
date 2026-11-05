<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\PaymentService;
use Illuminate\Console\Command;
use Throwable;

class ExpireSubscriptionCheckouts extends Command
{
    protected $signature = 'subscriptions:expire-checkouts';

    protected $description = 'Reconcile and expire abandoned card subscription checkouts';

    public function handle(PaymentGatewayManager $gateways, PaymentService $payments, AuditLogger $audit): int
    {
        Subscription::query()->where('method', 'card')->where('status', 'pending')
            ->where('created_at', '<=', now()->subDay())->orderBy('id')->chunkById(100, function ($subscriptions) use ($gateways, $payments, $audit) {
                foreach ($subscriptions as $subscription) {
                    $provider = $subscription->payment_gateway ?? (string) config('services.payments.default', 'monnify');
                    try {
                        $verified = $gateways->driver($provider)->verify('sub_'.$subscription->id);
                    } catch (Throwable $e) {
                        report($e);

                        continue;
                    }

                    if ($verified->status === 'success') {
                        $payments->process($provider, 'expiry_verify_'.$subscription->id, 'sub_'.$subscription->id,
                            'success', $verified->amountMinor, $verified->raw);
                    } elseif (in_array($verified->status, ['pending', 'failed'], true) && $subscription->fresh()->status === 'pending') {
                        $subscription->update(['status' => 'expired']);
                        $audit->record('subscription.checkout_expired', $subscription);
                    }
                }
            });

        return self::SUCCESS;
    }
}
