<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwitchProfileRequest;
use App\Models\LearnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Switch the active child profile. The route's `can:view,learner` guard has
     * already confirmed the caller may access this learner (parent/owner/self/
     * same-tenant staff — see LearnerProfilePolicy).
     */
    public function switch(SwitchProfileRequest $request, LearnerProfile $learner): JsonResponse
    {
        $fromLearnerId = $request->integer('from_learner_id') ?: null;

        // A PIN is required to enter a profile the parent gave one to, and
        // always when hopping from one already-active child profile to a
        // different one — otherwise an un-PIN'd sibling profile is a free
        // door between kids sharing the device.
        $fromLearner = $fromLearnerId ? LearnerProfile::find($fromLearnerId) : null;
        $switchingBetweenChildren = $fromLearner !== null
            && $fromLearner->id !== $learner->id
            && $fromLearner->user_id === null
            && $learner->user_id === null;

        if ($learner->parental_pin === null) {
            if ($switchingBetweenChildren) {
                return response()->json([
                    'error' => [
                        'code' => 'pin_not_set',
                        'message' => "Ask a parent to set a PIN for {$learner->display_name} before switching to it from another profile.",
                        'status' => 422,
                    ],
                ], 422);
            }

            return response()->json(['data' => ['active_learner_id' => $learner->id]]);
        }

        if (! filled($request->input('pin')) || ! Hash::check((string) $request->input('pin'), $learner->parental_pin)) {
            return response()->json([
                'error' => ['code' => 'invalid_pin', 'message' => 'Incorrect PIN.', 'status' => 403],
            ], 403);
        }

        return response()->json(['data' => ['active_learner_id' => $learner->id]]);
    }
}
