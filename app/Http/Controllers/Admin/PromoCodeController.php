<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Models\PromoCode;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $promos = PromoCode::withCount('redemptions')->latest()->get();

        return response()->json(['data' => $promos]);
    }

    public function store(StorePromoCodeRequest $request): JsonResponse
    {
        $promo = PromoCode::create($request->validated() + [
            'redeemed_count' => 0,
            'status' => 'active',
        ]);

        return response()->json(['data' => ['id' => $promo->id, 'code' => $promo->code, 'status' => $promo->status]], 201);
    }

    public function destroy(PromoCode $promoCode): JsonResponse
    {
        $before = ['status' => $promoCode->status];
        $promoCode->update(['status' => 'inactive']);

        $this->audit->record('promocode.deactivated', $promoCode, $before, ['status' => 'inactive']);

        return response()->json(['data' => ['id' => $promoCode->id, 'status' => $promoCode->status]]);
    }
}
