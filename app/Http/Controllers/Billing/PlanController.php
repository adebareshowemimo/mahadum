<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('price_minor')->get()->map(fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'price_minor' => $p->price_minor,
            'currency' => $p->currency,
            'interval' => $p->interval,
            'audience' => $p->audience,
            'max_profiles' => $p->max_profiles,
            'features' => $p->features,
        ]);

        return response()->json(['data' => $plans]);
    }
}
