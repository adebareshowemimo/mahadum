<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearnerPathNode;
use App\Models\LearnerProfile;
use Illuminate\Http\JsonResponse;

class PathController extends Controller
{
    /** Authorized by the route's can:view,learner guard. */
    public function show(LearnerProfile $learner): JsonResponse
    {
        $nodes = LearnerPathNode::whereHas('enrollment', fn ($q) => $q->where('learner_profile_id', $learner->id))
            ->with(['lesson.courseLevel'])
            ->orderBy('position')
            ->get();

        $units = $nodes
            ->groupBy(fn ($n) => $n->lesson->courseLevel->title)
            ->map(fn ($group, $title) => [
                'title' => $title,
                'nodes' => $group->map(fn ($n) => [
                    'lesson_id' => $n->lesson_id,
                    'title' => $n->lesson->title,
                    'state' => $n->state,
                    'position' => $n->position,
                ])->values()->all(),
            ])->values();

        return response()->json(['data' => ['units' => $units]]);
    }
}
