<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ChangeSubscriptionRequest;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\PaymentService;
use App\Services\Billing\PromoException;
use App\Services\Billing\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private PromoService $promos,
        private PaymentService $payments,
    ) {}

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

        try {
            $data = $this->createSubscription(
                $request->user(),
                $plan,
                $request->string('method')->value(),
                $request->input('promo_code'),
            );
        } catch (PromoException $e) {
            return response()->json(['error' => ['code' => $e->reason, 'message' => $e->getMessage(), 'status' => 422]], 422);
        }

        return response()->json(['data' => $data], 201);
    }

    /**
     * Switch the caller's own subscription to a different plan: cancels the
     * current one and creates a fresh one for the target plan in a single call,
     * so the SPA doesn't have to drive cancel + subscribe as two separate steps.
     * Card subscriptions still go through the normal checkout/webhook activation.
     */
    public function change(Subscription $subscription, ChangeSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $subscription->subscriber_type === User::class && (int) $subscription->subscriber_id === (int) $user->id,
            403,
            'Not your subscription.',
        );

        if ($subscription->status === 'cancelled') {
            return response()->json([
                'error' => ['code' => 'subscription_cancelled', 'message' => 'This subscription is already cancelled.', 'status' => 422],
            ], 422);
        }

        $plan = Plan::findOrFail($request->integer('plan_id'));

        if ((int) $subscription->plan_id === $plan->id) {
            return response()->json([
                'error' => ['code' => 'same_plan', 'message' => 'You are already on this plan.', 'status' => 422],
            ], 422);
        }

        try {
            $data = $this->createSubscription($user, $plan, $request->string('method')->value(), $request->input('promo_code'));
        } catch (PromoException $e) {
            return response()->json(['error' => ['code' => $e->reason, 'message' => $e->getMessage(), 'status' => 422]], 422);
        }

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

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

    /**
     * Resume a still-`pending` card subscription: first ask the gateway directly
     * whether it was actually paid (covers the webhook not having arrived yet, or
     * being unable to reach this environment), and activate immediately if so.
     * Otherwise open a fresh checkout on the same `sub_<id>` reference so the
     * subscriber can pay again without a duplicate subscription row.
     */
    public function retry(Subscription $subscription, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $subscription->subscriber_type === User::class && (int) $subscription->subscriber_id === (int) $user->id,
            403,
            'Not your subscription.',
        );

        if ($subscription->method !== 'card') {
            return response()->json([
                'error' => ['code' => 'not_retryable', 'message' => 'Only card payments can be retried.', 'status' => 422],
            ], 422);
        }

        if ($subscription->status === 'active') {
            return response()->json(['data' => ['status' => 'active']]);
        }

        if (in_array($subscription->status, ['cancelled', 'refunded'], true)) {
            return response()->json([
                'error' => ['code' => 'subscription_cancelled', 'message' => 'This subscription is no longer active.', 'status' => 422],
            ], 422);
        }

        $reference = 'sub_'.$subscription->id;
        $gateway = $this->gateways->driver();

        $verified = $gateway->verify($reference);
        if ($verified->status === 'success') {
            $outcome = $this->payments->process(
                (string) config('services.payments.default', 'monnify'),
                'manual_verify_'.$subscription->id.'_'.now()->getTimestamp(),
                $reference,
                'success',
                $verified->amountMinor,
                $verified->raw,
            );

            return response()->json(['data' => ['status' => $subscription->fresh()->status, 'outcome' => $outcome]]);
        }

        // Not paid (or failed) — reopen checkout on the same reference so a retried
        // payment still correlates to this subscription rather than creating a new one.
        $checkout = $gateway->initialize(
            $reference,
            (int) $subscription->plan->price_minor,
            (string) $user->email,
            ['purpose' => 'subscription', 'subscription_id' => $subscription->id],
        );

        if ($checkout->providerReference !== null) {
            $subscription->update(['gateway_txn_ref' => $checkout->providerReference]);
        }

        return response()->json(['data' => [
            'status' => $subscription->status,
            'payment_reference' => $reference,
            'checkout_url' => $checkout->checkoutUrl,
        ]]);
    }

    /**
     * Shared subscription-creation logic used by both a brand-new subscribe and
     * a plan change. Returns the response payload; throws PromoException on an
     * invalid code so callers can render a consistent 422.
     *
     * @return array<string, mixed>
     */
    private function createSubscription(User $user, Plan $plan, string $method, ?string $promoCode): array
    {
        $chargeMinor = (int) $plan->price_minor;
        $outcome = null;
        if ($promoCode) {
            $outcome = $this->promos->evaluate($promoCode, $plan, $user);
            $chargeMinor = $outcome->finalMinor;
        }

        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'method' => $method,
            'status' => $method === 'card' ? 'pending' : 'active',
        ]);
        $subscription->subscriber()->associate($user);

        if ($method !== 'card') {
            $subscription->started_at = now();
            $subscription->renews_at = $this->renewsAt($plan);
        }
        $subscription->save();

        if ($outcome !== null) {
            $this->promos->redeem($outcome->promo, $user, $subscription);
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
                (string) $user->email,
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

        return $data;
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
