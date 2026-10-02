<?php

namespace App\Jobs;

use App\Models\DataBundlePurchase;
use App\Services\Billing\DataBundleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDataBundlePurchase implements ShouldQueue
{
    use Queueable;

    public int $tries = 360;

    public int $timeout = 85;

    public int $backoff = 30;

    public function __construct(public int $purchaseId)
    {
        $this->onQueue('data-bundles');
    }

    public function handle(DataBundleService $service): void
    {
        $purchase = DataBundlePurchase::find($this->purchaseId);
        if (! $purchase) {
            return;
        }
        $service->refresh($purchase);
        if (in_array($purchase->status, ['awaiting_payment', 'processing'], true)) {
            $this->release(30);
        }
    }
}
