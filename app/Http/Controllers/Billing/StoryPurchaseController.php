<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Story;
use App\Models\StoryPurchase;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\StoryPaymentService;
use App\Services\Learning\StoryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StoryPurchaseController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private StoryPaymentService $payments,
        private StoryAccess $access,
    ) {}

    public function store(Request $request, Story $story): JsonResponse
    {
        $data = $request->validate([
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'provider' => ['required', 'in:monnify,paystack,flutterwave'],
        ]);
        $learner = LearnerProfile::findOrFail($data['learner_id']);
        abort_unless($this->access->canPurchase($learner, $request->user()), 403,
            'A parent or adult learner must purchase stories.');
        abort_unless($story->published_at && $this->access->isVisible($story, $learner, $request->user()), 404);
        abort_unless(in_array($data['provider'], $this->gateways->available(), true), 503,
            'This payment provider is unavailable. No payment has been taken.');

        $purchase = Cache::lock('story-purchase:'.$request->user()->id.':'.$story->id, 20)->block(3, function () use ($request, $story, $data) {
            $existing = StoryPurchase::where('user_id', $request->user()->id)->where('story_id', $story->id)
                ->whereIn('status', ['paid', 'pending'])->latest()->first();
            if ($existing && ($existing->status === 'paid' || $existing->checkout_url)) {
                return $existing;
            }

            $purchase = $existing ?? StoryPurchase::create([
                'story_id' => $story->id, 'user_id' => $request->user()->id,
                'provider' => $data['provider'], 'amount_minor' => Story::PRICE_MINOR,
                'reference' => 'story_'.Str::uuid(), 'status' => 'pending',
            ]);
            try {
                $checkout = $this->gateways->driver($purchase->provider)->initialize(
                    $purchase->reference, $purchase->amount_minor, (string) $request->user()->email,
                    ['purpose' => 'story', 'story_id' => $story->id, 'purchase_id' => $purchase->id],
                );
                abort_unless(! empty($checkout->checkoutUrl), 502, 'The provider did not return a checkout link.');
                $purchase->update(['checkout_url' => $checkout->checkoutUrl, 'gateway_txn_ref' => $checkout->providerReference]);
            } catch (\Throwable $error) {
                // The provider may have accepted a timed-out request. Keep the
                // reference for verification instead of offering another charge.
                report($error);
            }

            return $purchase;
        });

        return response()->json(['data' => $this->payload($purchase)], $purchase->wasRecentlyCreated ? 201 : 200);
    }

    public function confirm(Request $request, StoryPurchase $purchase): JsonResponse
    {
        abort_unless($purchase->user_id === $request->user()->id, 403);
        if ($purchase->status !== 'pending') {
            return response()->json(['data' => $this->payload($purchase)]);
        }
        $verified = $this->gateways->driver($purchase->provider)->verify($purchase->reference);
        if ($verified->status === 'success') {
            $this->payments->settle($purchase, $purchase->provider, $verified->amountMinor,
                $verified->raw, 'verify-story:'.$purchase->id);
        }

        return response()->json(['data' => $this->payload($purchase->fresh())]);
    }

    private function payload(StoryPurchase $purchase): array
    {
        return [
            'id' => $purchase->id, 'story_id' => $purchase->story_id,
            'status' => $purchase->status, 'provider' => $purchase->provider,
            'amount_minor' => $purchase->amount_minor, 'checkout_url' => $purchase->checkout_url,
        ];
    }
}
