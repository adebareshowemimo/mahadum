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
        // PIN gate: only enforced for pin-protected child profiles.
        if ($learner->parental_pin_protected && ! $this->pinMatches($request, $learner)) {
            return response()->json([
                'error' => ['code' => 'invalid_pin', 'message' => 'Incorrect parental PIN.', 'status' => 403],
            ], 403);
        }

        return response()->json(['data' => ['active_learner_id' => $learner->id]]);
    }

    private function pinMatches(SwitchProfileRequest $request, LearnerProfile $learner): bool
    {
        $hash = $learner->family?->parental_pin;

        // No PIN configured on the family → fall back to requiring any PIN.
        if (! $hash) {
            return filled($request->input('pin'));
        }

        return filled($request->input('pin'))
            && Hash::check((string) $request->input('pin'), $hash);
    }
}
