<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PurchaseDataBundleRequest;
use App\Models\DataBundlePurchase;
use App\Services\Billing\DataBundleService;
use App\Services\Billing\MonnifyBills;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DataBundleController extends Controller
{
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
        $purchase = $service->purchase($request->user(), $request->validated(), (string) $request->header('Idempotency-Key'));

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
