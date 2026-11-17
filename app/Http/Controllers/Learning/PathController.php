<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearnerPathNode;
use App\Models\LearnerProfile;
use App\Services\Learning\LearningGoals;
use App\Services\Learning\LessonAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class PathController extends Controller
{
    /** Authorized by the route's can:view,learner guard. */
    public function show(LearnerProfile $learner, LearningGoals $goals): JsonResponse
    {
        $nodes = LearnerPathNode::whereHas('enrollment', fn ($q) => $q->where('learner_profile_id', $learner->id))
            ->whereHas('lesson.courseLevel')
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
                $state = $node->state === 'completed' ? 'completed' : (($tier !== 'paid' && ($blocked[$node->enrollment_id] ?? false)) ? 'locked' : 'active');
                if ($state !== 'completed' && $tier !== 'paid') {
                    $blocked[$node->enrollment_id] = true;
                }
            }

            return ['unit' => $node->lesson->courseLevel->title,
                'lesson_id' => $node->lesson_id, 'title' => $node->lesson->title, 'state' => $state,
                'search_text' => mb_strtolower($node->lesson->title.' '.($node->lesson->summary ?? '')),
                'access_reason' => $permission['reason'], 'is_free' => (bool) $node->lesson->courseLevel->is_free, 'is_free_preview' => (bool) $node->lesson->courseLevel->is_free,
                'position' => $node->position];
        });
        $units = $display->groupBy('unit')->map(fn ($group, $title) => [
            'title' => $title, 'nodes' => $group->map(fn ($node) => Arr::except($node, ['unit', 'search_text']))->values()->all(),
        ])->values();

        $goal = $goals->find($learner->learning_goal);
        $suggested = $goal === null ? collect() : $display
            ->filter(function ($node) use ($goal) {
                if ($node['state'] !== 'active') {
                    return false;
                }

                return collect($goal['keywords'])->contains(fn ($keyword) => str_contains($node['search_text'], $keyword));
            })
            ->take(3)
            ->map(fn ($node) => ['lesson_id' => $node['lesson_id'], 'title' => $node['title']])
            ->values();

        return response()->json(['data' => [
            'units' => $units,
            'learning_goal' => $goal === null ? null : ['key' => $goal['key'], 'label' => $goal['label'], 'message' => $goal['message']],
            'completed_lessons' => $display->where('state', 'completed')->count(),
            'suggested_lessons' => $suggested,
        ]]);
    }
}
