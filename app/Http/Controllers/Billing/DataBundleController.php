<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PurchaseDataBundleRequest;
use App\Models\DataBundlePurchase;
use App\Services\AuditLogger;
use App\Services\Billing\DataBundleService;
use App\Services\Billing\DataCatalogue;
use App\Services\Billing\FlutterwaveBills;
use App\Services\Billing\MonnifyBills;
use App\Services\Billing\MonnifyBillsRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DataBundleController extends Controller
{
    public function providers(DataCatalogue $catalogue): JsonResponse
    {
        return response()->json(['data' => $catalogue->providers()]);
    }

    public function networks(DataCatalogue $catalogue): JsonResponse
    {
        return response()->json(['data' => $catalogue->networks()]);
    }

    public function offers(Request $request, DataCatalogue $catalogue): JsonResponse
    {
        $input = $request->validate(['network' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => $catalogue->offers($input['network'])]);
    }

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

    public function billers(Request $request, MonnifyBills $bills, FlutterwaveBills $flutterwave): JsonResponse
    {
        $provider = $request->validate(['provider' => ['sometimes', Rule::in(['monnify', 'flutterwave'])]])['provider'] ?? 'monnify';

        return response()->json(['data' => ($provider === 'flutterwave' ? $flutterwave : $bills)->billers()]);
    }

    public function index(Request $request, MonnifyBills $bills, FlutterwaveBills $flutterwave): JsonResponse
    {
        $input = $request->validate(['biller_code' => ['required', 'string', 'max:255'],
            'provider' => ['sometimes', Rule::in(['monnify', 'flutterwave'])]]);

        return response()->json(['data' => (($input['provider'] ?? 'monnify') === 'flutterwave' ? $flutterwave : $bills)->products($input['biller_code'])]);
    }

    public function purchase(PurchaseDataBundleRequest $request, DataBundleService $service): JsonResponse
    {
        $audit = app(AuditLogger::class);
        $details = ['source' => 'server', 'provider' => $request->validated('provider', 'monnify'), 'biller_code' => $request->validated('biller_code'), 'product_code' => $request->validated('product_code'), 'amount_minor' => $request->validated('amount_minor')];
        $audit->record('billing.data_bundle.purchase_requested', $request->user(), [], $details);
        try {
            $purchase = $service->purchase($request->user(), $request->validated(), (string) $request->header('Idempotency-Key'));
        } catch (\Throwable $error) {
            $audit->record('billing.data_bundle.purchase_rejected', $request->user(), [], array_merge($details, ['error_type' => class_basename($error)]));
            throw $error;
        }

        return response()->json(['data' => $service->response($purchase)], 201);
    }

    public function history(Request $request, DataBundleService $service): JsonResponse
    {
        $purchases = DataBundlePurchase::where('user_id', $request->user()->id)
            ->whereNotNull('payment_reference')
            ->orderByDesc('id')->limit(20)->get();

        // Listing history must not initiate payment verification or another vend.
        return response()->json(['data' => $purchases->map(fn (DataBundlePurchase $purchase) => $service->response($purchase))]);
    }

    public function show(Request $request, DataBundlePurchase $purchase, DataBundleService $service): JsonResponse
    {
        abort_unless($purchase->user_id === $request->user()->id, 403);
        try {
            $service->refresh($purchase);
        } catch (MonnifyBillsRequestException|RequestException|ConnectionException) {
            $data = $service->response($purchase->fresh());
            $data['check_issue'] = 'provider_unavailable';

            return response()->json(['data' => $data]);
        }

        return response()->json(['data' => $service->response($purchase)]);
    }

    public function verify(Request $request, DataBundleService $service): JsonResponse
    {
        $input = $request->validate(['payment_reference' => ['required', 'string', 'regex:/^data_[a-f0-9]{64}$/D']]);
        $purchase = DataBundlePurchase::where('user_id', $request->user()->id)
            ->where('payment_reference', $input['payment_reference'])->firstOrFail();

        // The redirect identifies the order; only server-side provider verification confirms payment.
        return $this->show($request, $purchase, $service);
    }
}
