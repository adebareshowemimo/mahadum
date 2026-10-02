<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataBundlePurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSalesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $base = DataBundlePurchase::query();
        if (! empty($filters['from'])) {
            $base->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $base->where('created_at', '<=', $filters['to'].' 23:59:59');
        }
        $statuses = (clone $base)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $summary = [
            'purchases' => (clone $base)->count(),
            'successful' => (int) ($statuses['success'] ?? 0),
            'delivered_sales_minor' => (int) (clone $base)->where('status', 'success')->sum('amount_minor'),
            'verified_payments_minor' => (int) (clone $base)->whereNotNull('paid_at')->sum('amount_minor'),
            'awaiting_payment' => (int) ($statuses['awaiting_payment'] ?? 0),
            'processing' => (int) ($statuses['processing'] ?? 0),
            'needs_attention' => (int) ($statuses['failed'] ?? 0) + (int) ($statuses['needs_review'] ?? 0),
            'payment_failed' => (int) ($statuses['payment_failed'] ?? 0),
        ];
        $networks = (clone $base)->selectRaw('operator as network, COUNT(*) as purchases, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful, SUM(CASE WHEN status = ? THEN amount_minor ELSE 0 END) as sales_minor', ['success', 'success'])->groupBy('operator')->get();
        $daily = (clone $base)->where('status', 'success')->selectRaw('DATE(created_at) as date, COUNT(*) as purchases, SUM(amount_minor) as sales_minor')->groupByRaw('DATE(created_at)')->orderBy('date')->get();
        $list = clone $base;
        if (! empty($filters['status'])) {
            $list->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $list->where(function ($query) use ($q) {
                $query->where('payment_reference', 'like', "%{$q}%")->orWhere('product_name', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$q}%"));
            });
        }
        $page = $list->with('user:id,first_name,last_name,email')->latest('id')->paginate(25);

        return response()->json([
            'summary' => $summary, 'statuses' => $statuses, 'networks' => $networks, 'daily' => $daily,
            'data' => collect($page->items())->map(fn (DataBundlePurchase $purchase) => [
                'id' => $purchase->id, 'buyer' => $purchase->user->email,
                'network' => $purchase->operator, 'plan' => $purchase->product_name,
                'amount_minor' => $purchase->amount_minor, 'status' => $purchase->status,
                'recipient_last4' => substr((string) $purchase->phone_number, -4),
                'reference' => $purchase->payment_reference, 'created_at' => $purchase->created_at?->toIso8601String(),
                'paid_at' => $purchase->paid_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
