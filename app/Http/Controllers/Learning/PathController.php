<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearnerPathNode;
use App\Models\LearnerProfile;
use App\Services\Learning\LessonAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class PathController extends Controller
{
    /** Authorized by the route's can:view,learner guard. */
    public function show(LearnerProfile $learner): JsonResponse
    {
        $nodes = LearnerPathNode::whereHas('enrollment', fn ($q) => $q->where('learner_profile_id', $learner->id))
            ->with(['lesson.courseLevel'])
            ->orderBy('position')
            ->get();

        $access = app(LessonAccess::class);
        $tier = $access->tier($learner);
        $blocked = [];
        $display = $nodes->map(function ($node) use ($access, $tier, $learner, &$blocked) {
            $permission = $access->content($learner, $node->lesson, $tier);
            $state = 'locked';
            if ($permission['allowed']) {
                $state = $node->state === 'completed' ? 'completed' : (($blocked[$node->enrollment_id] ?? false) ? 'locked' : 'active');
                if ($state !== 'completed') {
                    $blocked[$node->enrollment_id] = true;
                }
            }

            return ['unit' => $node->lesson->courseLevel->title,
                'lesson_id' => $node->lesson_id, 'title' => $node->lesson->title, 'state' => $state,
                'access_reason' => $permission['reason'], 'is_free_preview' => (bool) $node->lesson->is_free_preview,
                'position' => $node->position];
        });
        $units = $display->groupBy('unit')->map(fn ($group, $title) => [
            'title' => $title, 'nodes' => $group->map(fn ($node) => Arr::except($node, ['unit']))->values()->all(),
        ])->values();

        return response()->json(['data' => ['units' => $units]]);
    }
}
