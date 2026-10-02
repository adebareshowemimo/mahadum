<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PurchaseDataBundleRequest;
use App\Models\DataBundlePurchase;
use App\Services\AuditLogger;
use App\Services\Billing\DataBundleService;
use App\Services\Billing\MonnifyBills;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DataBundleController extends Controller
{
    public function event(Request $request, AuditLogger $audit): JsonResponse
    {
        $input = $request->validate([
            'event' => ['required', Rule::in(['page_viewed', 'network_selected', 'plan_selected', 'search_changed', 'filter_changed', 'sort_changed', 'recipient_completed', 'consent_changed', 'checkout_clicked', 'checkout_opened', 'status_check_clicked', 'reset_clicked', 'support_clicked', 'catalogue_retry_clicked', 'catalogue_loaded', 'catalogue_failed', 'checkout_failed', 'status_viewed'])],
            'session_id' => ['required', 'uuid'],
            'purchase_id' => ['nullable', 'integer'],
            'biller_code' => ['nullable', 'string', 'max:100'],
            'product_code' => ['nullable', 'string', 'max:100'],
            'value' => ['nullable', 'string', 'max:60'],
            'count' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $purchase = isset($input['purchase_id']) ? DataBundlePurchase::findOrFail($input['purchase_id']) : null;
        abort_if($purchase && $purchase->user_id !== $request->user()->id, 403);
        $event = $input['event'];
        unset($input['event']);
        $audit->record('billing.data_bundle.ui.'.$event, $purchase ?? $request->user(), [], array_merge($input, ['source' => 'browser']));

        return response()->json(['recorded' => true], 201);
    }

    public function billers(MonnifyBills $bills): JsonResponse
    {
        return response()->json(['data' => $bills->billers()]);
    }

    public function index(Request $request, MonnifyBills $bills): JsonResponse
    {
        $input = $request->validate(['biller_code' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => $bills->products($input['biller_code'])]);
    }

    public function purchase(PurchaseDataBundleRequest $request, DataBundleService $service): JsonResponse
    {
        $audit = app(AuditLogger::class);
        $details = ['source' => 'server', 'biller_code' => $request->validated('biller_code'), 'product_code' => $request->validated('product_code'), 'amount_minor' => $request->validated('amount_minor')];
        $audit->record('billing.data_bundle.purchase_requested', $request->user(), [], $details);
        try {
            $purchase = $service->purchase($request->user(), $request->validated(), (string) $request->header('Idempotency-Key'));
        } catch (\Throwable $error) {
            $audit->record('billing.data_bundle.purchase_rejected', $request->user(), [], array_merge($details, ['error_type' => class_basename($error)]));
            throw $error;
        }

        return response()->json(['data' => $service->response($purchase)], 201);
    }

    public function show(Request $request, DataBundlePurchase $purchase, DataBundleService $service): JsonResponse
    {
        abort_unless($purchase->user_id === $request->user()->id, 403);
        try {
            $service->refresh($purchase);
        } catch (RequestException|ConnectionException) {
            throw new HttpException(503, 'Monnify is unavailable. Your purchase reference is saved; check its status again later.');
        }

        return response()->json(['data' => $service->response($purchase)]);
    }
}
