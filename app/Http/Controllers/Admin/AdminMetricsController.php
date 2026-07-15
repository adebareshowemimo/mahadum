<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\TelcoBillingAttempt;
use App\Models\User;
use App\Models\WalletFundingTransaction;
use Illuminate\Http\JsonResponse;

class AdminMetricsController extends Controller
{
    /** Platform-wide KPIs (super_admin, unscoped). */
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'users' => User::count(),
            'users_by_type' => [
                'school' => User::whereHas('organizations')->count(),
                'family' => User::whereDoesntHave('organizations')->whereHas('ownedFamilies')->count(),
                'single' => User::whereDoesntHave('organizations')->whereDoesntHave('ownedFamilies')->count(),
            ],
            'organizations' => Organization::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'subscriptions' => Subscription::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'revenue_minor' => (int) WalletFundingTransaction::where('status', 'success')->sum('amount_minor'),
            'languages' => Language::where('is_active', true)->count(),
        ]]);
    }

    public function billingHealth(): JsonResponse
    {
        $attempts = TelcoBillingAttempt::count();
        $success = TelcoBillingAttempt::where('result', 'success')->count();

        return response()->json(['data' => [
            'telco' => [
                'attempts' => $attempts,
                'success' => $success,
                'success_rate' => $attempts > 0 ? round($success / $attempts, 4) : null,
            ],
            'funding' => WalletFundingTransaction::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'subscriptions' => Subscription::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]]);
    }
}
