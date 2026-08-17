<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\TelcoOtpRequest;
use App\Http\Requests\Billing\TelcoOtpVerifyRequest;
use App\Http\Requests\Billing\TelcoSubscribeRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\TelcoOtpService;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * New airtime-subscription enrolment (OTP + subscribe) is deprecated platform-wide,
 * gated off by the `feature.telco_billing` setting (admin-editable, defaults off —
 * see config/settings.php). `status` stays open and the daily billing/grace jobs
 * keep running so any pre-existing airtime subscriptions wind down normally
 * instead of breaking outright.
 */
class TelcoController extends Controller
{
    public function __construct(private TelcoOtpService $otp, private AuditLogger $audit, private Settings $settings) {}

    /** Step 1: send a one-time code to the MSISDN the caller wants to enrol. */
    public function requestOtp(TelcoOtpRequest $request): JsonResponse
    {
        $this->abortUnlessEnabled();

        $otp = $this->otp->request(
            $request->user(),
            (string) $request->string('msisdn'),
            (string) $request->string('operator'),
        );

        return response()->json(['data' => [
            'expires_at' => $otp->expires_at,
            'msisdn' => $otp->msisdn,
            'simulated' => $this->isSimulated(),
        ]], 202);
    }

    /** Step 2: confirm the code, marking the MSISDN verified for enrolment. */
    public function verifyOtp(TelcoOtpVerifyRequest $request): JsonResponse
    {
        $this->abortUnlessEnabled();

        $ok = $this->otp->verify(
            $request->user(),
            (string) $request->string('msisdn'),
            (string) $request->string('code'),
        );

        abort_unless($ok, 422, 'Invalid or expired code.');

        return response()->json(['data' => ['verified' => true]]);
    }

    /**
     * Step 3: opt into airtime (VAS) billing. Requires a fresh, verified OTP for
     * this MSISDN (consumed here, single-use). Creates the subscription + a
     * telco_subscription with a daily charge; the daily engine
     * (RunDailyTelcoBilling) drives the charge lifecycle from there.
     */
    public function subscribe(TelcoSubscribeRequest $request): JsonResponse
    {
        $this->abortUnlessEnabled();

        abort_unless(
            $this->otp->consumeVerified($request->user(), (string) $request->string('msisdn')),
            403,
            'Phone number not verified. Request and confirm an OTP first.',
        );

        $plan = Plan::findOrFail($request->integer('plan_id'));

        // Airtime billing debits a monthly plan's price/30 each day. Enrolling an
        // annual (or other-cadence) plan would over-charge (e.g. ₦60,000/30 = ₦2,000/day),
        // so restrict daily VAS billing to monthly tiers.
        abort_unless($plan->interval === 'month', 422, 'Airtime billing is only available for monthly plans.');

        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'method' => 'airtime',
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);
        $subscription->subscriber()->associate($request->user());
        $subscription->save();

        $telco = $subscription->telco()->create([
            'msisdn' => $request->string('msisdn'),
            'operator' => $request->string('operator'),
            'daily_amount_minor' => max(1, (int) round($plan->price_minor / 30)),
            'state' => 'active',
            'next_attempt_at' => now()->addDay()->setTime(2, 0),
        ]);

        $this->audit->record('telco.enrolled', $subscription, [], [
            'operator' => $telco->operator,
            'msisdn' => $telco->msisdn,
            'daily_amount_minor' => $telco->daily_amount_minor,
        ]);

        return response()->json(['data' => [
            'subscription_id' => $subscription->id,
            'state' => $telco->state,
            'msisdn' => $telco->msisdn,
            'next_attempt_at' => $telco->next_attempt_at,
            'simulated' => $this->isSimulated(),
        ]], 201);
    }

    private function abortUnlessEnabled(): void
    {
        abort_unless(
            (bool) $this->settings->get('feature.telco_billing'),
            403,
            'Airtime billing is no longer available. Please choose a card or bank plan.',
        );
    }

    /**
     * True when no live operator SDP is configured, so TelcoGatewayManager is
     * serving a NullTelcoGateway: charges succeed deterministically and no OTP
     * SMS is actually sent. Surfaced to the client so the UI can say so rather
     * than reporting a fake success — the same honesty rule the email layer
     * applies when MAIL_MAILER is `log`.
     */
    private function isSimulated(): bool
    {
        return ! (bool) config('services.telco.live');
    }

    public function status(Request $request): JsonResponse
    {
        $subscription = Subscription::where('subscriber_type', User::class)
            ->where('subscriber_id', $request->user()->id)
            ->where('method', 'airtime')
            ->with('telco')
            ->latest()
            ->first();

        abort_if(! $subscription || ! $subscription->telco, 404, 'No airtime subscription.');

        $telco = $subscription->telco;

        return response()->json(['data' => [
            'state' => $telco->state,
            'grace_until' => $telco->grace_until,
            'next_attempt_at' => $telco->next_attempt_at,
        ]]);
    }
}
