<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\PromoException;
use App\Services\Billing\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private PaymentGatewayManager $gateways, private PromoService $promos) {}

    /**
     * Preview a promo code against a plan before checkout: returns the discount +
     * final amount, or a 422 with a human reason. No side effects.
     */
    public function promoPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        try {
            $outcome = $this->promos->evaluate($validated['code'], $plan, $request->user());
        } catch (PromoException $e) {
            return response()->json(['error' => ['code' => $e->reason, 'message' => $e->getMessage(), 'status' => 422]], 422);
        }

        return response()->json(['data' => [
            'code' => $outcome->promo->code,
            'price_minor' => (int) $plan->price_minor,
            'discount_minor' => $outcome->discountMinor,
            'final_minor' => $outcome->finalMinor,
        ]]);
    }

    /** The caller's subscriptions, newest first (billing history). */
    public function index(): JsonResponse
    {
        $user = request()->user();

        $subscriptions = Subscription::with('plan')
            ->where('subscriber_type', User::class)
            ->where('subscriber_id', $user->id)
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'status' => $s->status,
                'method' => $s->method,
                'plan_code' => $s->plan->code,
                'plan_name' => $s->plan->name,
                'price_minor' => $s->plan->price_minor,
                'started_at' => $s->started_at,
                'renews_at' => $s->renews_at,
                'cancelled_at' => $s->cancelled_at,
            ]);

        return response()->json(['data' => $subscriptions]);
    }

    /**
     * Create a subscription. Card subscriptions start `pending` and are activated
     * by the gateway webhook (correlated via the `sub_<id>` reference). Invoice
     * (school) subscriptions are active immediately and settled out-of-band.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $plan = Plan::findOrFail($request->integer('plan_id'));
        $method = $request->string('method')->value();

        // Optional promo code — validated up-front so an invalid code fails the
        // whole request (422) before any subscription row is created.
        $chargeMinor = (int) $plan->price_minor;
        $outcome = null;
        if ($code = $request->input('promo_code')) {
            try {
                $outcome = $this->promos->evaluate((string) $code, $plan, $request->user());
                $chargeMinor = $outcome->finalMinor;
            } catch (PromoException $e) {
                return response()->json(['error' => ['code' => $e->reason, 'message' => $e->getMessage(), 'status' => 422]], 422);
            }
        }

        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'method' => $method,
            'status' => $method === 'card' ? 'pending' : 'active',
        ]);
        $subscription->subscriber()->associate($request->user());

        if ($method !== 'card') {
            $subscription->started_at = now();
            $subscription->renews_at = $this->renewsAt($plan);
        }
        $subscription->save();

        if ($outcome !== null) {
            $this->promos->redeem($outcome->promo, $request->user(), $subscription);
        }

        $data = ['subscription_id' => $subscription->id, 'status' => $subscription->status];
        if ($outcome !== null) {
            $data['discount_minor'] = $outcome->discountMinor;
            $data['charged_minor'] = $chargeMinor;
        }

        if ($method === 'card') {
            // Open the hosted checkout; the webhook activates it via this reference.
            $reference = 'sub_'.$subscription->id;
            $checkout = $this->gateways->driver()->initialize(
                $reference,
                $chargeMinor,
                (string) $request->user()->email,
                ['purpose' => 'subscription', 'subscription_id' => $subscription->id],
            );

            // Record the gateway's own transaction id when it returns one, so a later
            // refund that doesn't echo our `sub_<id>` reference (e.g. Monnify) correlates.
            if ($checkout->providerReference !== null) {
                $subscription->update(['gateway_txn_ref' => $checkout->providerReference]);
            }

            $data['payment_reference'] = $reference;
            $data['checkout_url'] = $checkout->checkoutUrl;
        }

        return response()->json(['data' => $data], 201);
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $user = request()->user();
        abort_unless(
            $subscription->subscriber_type === User::class && (int) $subscription->subscriber_id === (int) $user->id,
            403,
            'Not your subscription.',
        );

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $message = $subscription->method === 'airtime'
            ? 'Subscription cancelled. To stop airtime billing, text STOP to 3600.'
            : 'Subscription cancelled.';

        return response()->json(['data' => ['status' => 'cancelled', 'message' => $message]]);
    }

    private function renewsAt(Plan $plan)
    {
        return match ($plan->interval) {
            'year' => now()->addYear(),
            'quarter' => now()->addMonths(3),
            'term' => now()->addMonths(4),
            'week' => now()->addWeek(),
            default => now()->addMonth(),
        };
    }
}
