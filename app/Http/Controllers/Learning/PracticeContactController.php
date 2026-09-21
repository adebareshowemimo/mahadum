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

        return response()->json(['data' => $eligibility->candidates($learner, $request->user(), $search)]);
    }
}
