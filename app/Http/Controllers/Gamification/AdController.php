<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\LearnerProfile;
use App\Services\Ads\AdAudience;
use App\Services\Ads\AdNetworkManager;
use App\Services\Ads\ManagedVideoGateway;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        $learner = $this->learnerForReward($request->integer('learner_id'));
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
        if (! app(AdAudience::class)->allowed($request->user(), $learner)
            || ! $gateway->available($placement)) {
            return response()->json(['data' => ['eligible' => false, 'reason' => 'unavailable']]);
        }

        $adRef = (string) Str::uuid();
        $impression->update(['ad_ref' => $adRef]);
        $video = $gateway instanceof ManagedVideoGateway ? $gateway->initialize($impression) : null;

        return response()->json(['data' => [
            'eligible' => true,
            'impression_id' => $impression->id,
            'ad_ref' => $adRef,
            'video' => $video,
        ]]);
    }

    public function progress(Request $request, AdImpression $impression, ManagedVideoGateway $video): JsonResponse
    {
        $input = $request->validate(['position_seconds' => ['required', 'numeric', 'between:0,300']]);

        return DB::transaction(function () use ($request, $impression, $video, $input) {
            $learner = LearnerProfile::whereKey($impression->learner_profile_id)->lockForUpdate()->firstOrFail();
            Gate::authorize('redeemReward', $learner);
            abort_unless(app(AdAudience::class)->allowed($request->user(), $learner), 422, 'Rewarded videos are no longer available for this account.');

            return response()->json(['data' => $video->progress($impression, (float) $input['position_seconds'])]);
        });
    }

    /** Client reports the ad finished playing; verified server-side before it can be redeemed. */
    public function complete(Request $request, AdImpression $impression): JsonResponse
    {
        return DB::transaction(function () use ($request, $impression) {
            $learner = LearnerProfile::whereKey($impression->learner_profile_id)->lockForUpdate()->firstOrFail();
            Gate::authorize('redeemReward', $learner);
            abort_unless(app(AdAudience::class)->allowed($request->user(), $learner), 422, 'Rewarded videos are no longer available for this account.');
            $impression = AdImpression::lockForUpdate()->findOrFail($impression->id);
            abort_unless($impression->ad_ref !== null, 422, 'No ad was requested for this impression.');
            $verified = $this->ads->driver()->verifyReward($impression->ad_ref);
            if ($verified && $impression->shown_at === null) {
                $impression->update(['shown_at' => now()]);
            }

            return response()->json(['data' => ['shown' => $verified]]);
        });
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
