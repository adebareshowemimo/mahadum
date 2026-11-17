<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwitchProfileRequest;
use App\Models\LearnerProfile;
use App\Services\Learning\LearningGoals;
use App\Support\ActiveChildProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function learningGoal(Request $request, LearnerProfile $learner, LearningGoals $goals): JsonResponse
    {
        abort_if(ActiveChildProfile::id($request) !== null, 403, 'Ask a grown-up to change this learning goal.');
        $validated = $request->validate(['learning_goal' => ['required', 'string', 'max:32']]);
        abort_unless($goals->find($validated['learning_goal']) !== null, 422, 'Choose an available learning goal.');
        $learner->update(['learning_goal' => $validated['learning_goal']]);

        return response()->json(['data' => ['learner_id' => $learner->id, 'learning_goal' => $learner->learning_goal]]);
    }

    /**
     * Switch the active child profile. The route's `can:view,learner` guard has
     * already confirmed the caller may access this learner (parent/owner/self/
     * same-tenant staff — see LearnerProfilePolicy).
     */
    public function switch(SwitchProfileRequest $request, LearnerProfile $learner): JsonResponse
    {
        $activeChildId = ActiveChildProfile::id($request);
        if ($activeChildId !== null && $activeChildId !== $learner->id) {
            $activeChild = LearnerProfile::findOrFail($activeChildId);
            if (! $activeChild->parental_pin || ! Hash::check((string) $request->input('pin'), $activeChild->parental_pin)) {
                return response()->json(['error' => ['code' => 'invalid_pin', 'message' => 'Enter the parent PIN to leave this child profile.', 'status' => 403]], 403);
            }
        }
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

        if ($learner->user_id === null && $learner->parental_pin === null) {
            return response()->json(['error' => ['code' => 'pin_not_set', 'message' => 'Set a parent PIN before opening this child profile.', 'status' => 422]], 422);
        }
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

            ActiveChildProfile::set($request, null);

            return response()->json(['data' => ['active_learner_id' => $learner->id]]);
        }

        if (! filled($request->input('pin')) || ! Hash::check((string) $request->input('pin'), $learner->parental_pin)) {
            return response()->json([
                'error' => ['code' => 'invalid_pin', 'message' => 'Incorrect PIN.', 'status' => 403],
            ], 403);
        }

        ActiveChildProfile::set($request, $learner->user_id === null ? $learner->id : null);

        return response()->json(['data' => ['active_learner_id' => $learner->id]]);
    }

    public function exit(Request $request): JsonResponse
    {
        $id = ActiveChildProfile::id($request);
        abort_unless($id !== null, 409, 'No child profile is active.');
        $child = LearnerProfile::findOrFail($id);
        $request->validate(['pin' => ['required', 'string', 'min:4', 'max:8']]);
        if (! $child->parental_pin || ! Hash::check((string) $request->input('pin'), $child->parental_pin)) {
            return response()->json(['error' => ['code' => 'invalid_pin', 'message' => 'Incorrect parent PIN.', 'status' => 403]], 403);
        }
        ActiveChildProfile::set($request, null);

        return response()->json(['data' => ['active_learner_id' => null]]);
    }
}
