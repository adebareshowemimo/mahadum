<?php

namespace App\Console\Commands;

use App\Models\DataBundlePurchase;
use App\Services\Billing\DataBundleService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileDataPurchases extends Command
{
    protected $signature = 'data-bundles:reconcile';

    protected $description = 'Recheck paid data orders that have no confirmed delivery';

    public function handle(DataBundleService $service): int
    {
        $checked = 0;
        $delivered = 0;
        $unresolved = 0;
        DataBundlePurchase::query()->whereNotNull('paid_at')->where('status', 'processing')
            ->orderBy('id')->chunkById(100, function ($orders) use ($service, &$checked, &$delivered, &$unresolved) {
                foreach ($orders as $order) {
                    $checked++;
                    try {
                        $service->refresh($order);
                    } catch (Throwable $error) {
                        report($error);
                    }
                    $order->refresh();
                    if ($order->status === 'success') {
                        $delivered++;
                    } else {
                        $unresolved++;
                        $this->warn("Order {$order->id}: {$order->status}; payment {$order->payment_reference}; vend {$order->vend_reference}");
                    }
                }
            });

        $this->info("Checked {$checked} paid orders; {$delivered} delivered; {$unresolved} need follow-up.");

        return $unresolved > 0 ? self::FAILURE : self::SUCCESS;
    }
}
