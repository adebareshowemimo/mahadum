<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends Controller
{
    public function store(StorePromoCodeRequest $request): JsonResponse
    {
        $promo = PromoCode::create($request->validated() + [
            'redeemed_count' => 0,
            'status' => 'active',
        ]);

        return response()->json(['data' => ['id' => $promo->id, 'code' => $promo->code, 'status' => $promo->status]], 201);
    }
}
