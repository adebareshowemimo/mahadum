<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Services\Learning\PracticeRecipientEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PracticeContactController extends Controller
{
    /** Authorized by the route's can:view,learner guard. */
    public function index(LearnerProfile $learner, Request $request, PracticeRecipientEligibility $eligibility): JsonResponse
    {
        $search = trim((string) $request->string('q'));
        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $eligibility->candidates($learner, $request->user(), $search)]);
    }
}
