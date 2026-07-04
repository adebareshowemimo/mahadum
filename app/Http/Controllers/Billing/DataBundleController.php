<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PurchaseDataBundleRequest;
use App\Models\DataBundlePurchase;
use Illuminate\Http\JsonResponse;

class DataBundleController extends Controller
{
    /** Catalogue of carrier data bundles (MB → price in minor units). */
    private const BUNDLES = [
        100 => 10000,   // 100MB  → ₦100
        500 => 30000,   // 500MB  → ₦300
        1024 => 50000,   // 1GB    → ₦500
    ];

    public function index(): JsonResponse
    {
        $bundles = collect(self::BUNDLES)->map(fn ($price, $mb) => [
            'bundle_mb' => $mb,
            'amount_minor' => $price,
            'currency' => 'NGN',
        ])->values();

        return response()->json(['data' => $bundles]);
    }

    public function purchase(PurchaseDataBundleRequest $request): JsonResponse
    {
        $mb = $request->integer('bundle_mb');
        abort_unless(array_key_exists($mb, self::BUNDLES), 422, 'Unknown bundle size.');

        $purchase = DataBundlePurchase::create([
            'user_id' => $request->user()->id,
            'operator' => $request->string('operator'),
            'bundle_mb' => $mb,
            'amount_minor' => self::BUNDLES[$mb],
            'status' => 'pending', // confirmed by telco DLR webhook
            'consent_at' => now(),
        ]);

        return response()->json(['data' => [
            'purchase_id' => $purchase->id,
            'status' => $purchase->status,
            'amount_minor' => $purchase->amount_minor,
        ]], 201);
    }
}
