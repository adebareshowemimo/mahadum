<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\SeatPricing;
use Illuminate\Http\JsonResponse;

/**
 * Public (unauthenticated) pricing for the marketing site: the consumer plans
 * (from the plans table, so it tracks whatever the admin console sets) and the
 * school seat bands (from the shared SeatPricing source of truth).
 */
class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        $consumer = Plan::whereIn('audience', ['individual', 'family'])
            ->orderBy('audience')
            ->orderBy('price_minor')
            ->get()
            ->map(fn (Plan $p) => [
                'code' => $p->code,
                'name' => $p->name,
                'audience' => $p->audience,
                'price_minor' => $p->price_minor,
                'currency' => $p->currency,
                'interval' => $p->interval,
                'max_profiles' => $p->max_profiles,
                'features' => [
                    'ads' => (bool) ($p->features['ads'] ?? false),
                    'offline_download' => (bool) ($p->features['offline_download'] ?? false),
                    'unlimited_hearts' => (bool) ($p->features['unlimited_hearts'] ?? false),
                    'family_dashboard' => (bool) ($p->features['family_dashboard'] ?? false),
                ],
            ]);

        return response()->json(['data' => [
            'free' => [
                'name' => 'Free',
                'blurb' => 'Full learning, forever. Ad-supported.',
            ],
            'consumer' => $consumer,
            'school' => [
                'term_months' => SeatPricing::TERM_MONTHS,
                'bands' => array_map(fn ($b) => [
                    'label' => $b['label'],
                    'registration_minor' => $b['registration_minor'],
                    'per_student_minor' => $b['per_student_minor'],
                ], SeatPricing::BANDS),
            ],
        ]]);
    }
}
