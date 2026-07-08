<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\LearnerProfile;
use App\Services\Ads\AdNetworkManager;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Ad-supported free tier (Rule 10: ads only between lesson nodes, never
 * interrupting an active lesson; COPPA/NDPA filtered). Every request is
 * logged as an AdImpression — including ones blocked by the age filter —
 * for compliance audit, regardless of whether an ad actually shows.
 */
class AdController extends Controller
{
    use ResolvesLearner;

    public function __construct(private AdNetworkManager $ads, private Settings $settings) {}

    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'placement' => ['required', 'in:post_lesson,rewarded_heart'],
        ]);

        $learner = $this->learner($request->integer('learner_id'));
        $placement = $request->string('placement')->value();
        $coppaPassed = $this->coppaPassed($learner);

        $impression = AdImpression::create([
            'learner_profile_id' => $learner->id,
            'placement' => $placement,
            'coppa_passed' => $coppaPassed,
        ]);

        if (! $coppaPassed) {
            return response()->json(['data' => ['eligible' => false, 'reason' => 'coppa']]);
        }

        $gateway = $this->ads->driver();
        if (! $gateway->available($placement)) {
            return response()->json(['data' => ['eligible' => false, 'reason' => 'unavailable']]);
        }

        $adRef = (string) Str::uuid();
        $impression->update(['ad_ref' => $adRef]);

        return response()->json(['data' => [
            'eligible' => true,
            'impression_id' => $impression->id,
            'ad_ref' => $adRef,
        ]]);
    }

    /** Client reports the ad finished playing; verified server-side before it can be redeemed. */
    public function complete(AdImpression $impression): JsonResponse
    {
        $this->learner($impression->learner_profile_id); // authorizes the caller against the impression's learner

        abort_unless($impression->ad_ref !== null, 422, 'No ad was requested for this impression.');
        abort_if($impression->shown_at !== null, 422, 'This ad has already been marked as shown.');

        $verified = $this->ads->driver()->verifyReward($impression->ad_ref);

        if ($verified) {
            $impression->update(['shown_at' => now()]);
        }

        return response()->json(['data' => ['shown' => $verified]]);
    }

    /**
     * Under the digital-consent age (Settings `compliance.minor_age`, same
     * gate FamilyController uses for the sign-up consent flow) → no ads.
     * Unknown date of birth is treated as a minor (safe default).
     */
    private function coppaPassed(LearnerProfile $learner): bool
    {
        if (! $learner->date_of_birth) {
            return false;
        }

        $minorAge = (int) $this->settings->get('compliance.minor_age', config('compliance.minor_age'));

        return Carbon::parse($learner->date_of_birth)->age >= $minorAge;
    }
}
