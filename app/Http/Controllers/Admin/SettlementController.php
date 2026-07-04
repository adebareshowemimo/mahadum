<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Payout;
use App\Models\TelcoBillingAttempt;
use Illuminate\Http\JsonResponse;

class SettlementController extends Controller
{
    /** Settlement overview: commissions, payouts, telco revenue. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'commissions' => Commission::selectRaw('status, COUNT(*) c, COALESCE(SUM(amount_minor),0) total')
                ->groupBy('status')->get()->keyBy('status'),
            'payouts' => Payout::selectRaw('status, COUNT(*) c, COALESCE(SUM(amount_minor),0) total')
                ->groupBy('status')->get()->keyBy('status'),
            'telco_revenue_minor' => (int) TelcoBillingAttempt::where('result', 'success')->sum('amount_minor'),
            // Cleared commissions whose subscription was later refunded — already
            // paid/payable, so finance must recover these manually (clawback flow).
            'clawback' => [
                'pending_count' => Commission::where('status', 'clawback_pending')->count(),
                'pending_minor' => (int) Commission::where('status', 'clawback_pending')->sum('amount_minor'),
            ],
        ]]);
    }
}
