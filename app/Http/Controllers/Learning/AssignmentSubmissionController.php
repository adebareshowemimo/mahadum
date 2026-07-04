<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreAssignmentSubmissionRequest;
use App\Models\AssignmentSubmission;
use App\Models\ComponentProgress;
use App\Models\LessonComponent;
use App\Models\MediaAsset;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssignmentSubmissionController extends Controller
{
    use ResolvesLearner;

    /**
     * Store a recorded assignment clip. The component is marked complete so the
     * lesson can be finished (learning is never gated on approval), but any coin
     * reward is escrowed on the submission (`coins_locked`) and released only
     * when a parent approves — never automatically.
     */
    public function store(StoreAssignmentSubmissionRequest $request, XapiRecorder $xapi): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        $component = LessonComponent::with('assignment')->findOrFail($request->integer('component_id'));

        abort_unless($component->type === 'assignment', 422, 'This component is not an assignment.');

        $submission = DB::transaction(function () use ($request, $learner, $component) {
            $assetId = null;
            if ($request->hasFile('media')) {
                $path = $request->file('media')->store('assignments', 'public');
                $assetId = MediaAsset::create([
                    'type' => str_starts_with((string) $request->file('media')->getMimeType(), 'video/') ? 'video' : 'audio',
                    'url' => $path,
                    'uploaded_by' => $request->user()->id,
                ])->id;
            }

            $submission = AssignmentSubmission::create([
                'learner_profile_id' => $learner->id,
                'lesson_component_id' => $component->id,
                'media_asset_id' => $assetId,
                'parent_review_status' => 'pending',
                'coins_locked' => (int) ($component->assignment->coin_reward ?? 0),
            ]);

            $progress = $this->lessonProgress($learner, $component->lesson);
            ComponentProgress::updateOrCreate(
                ['lesson_progress_id' => $progress->id, 'lesson_component_id' => $component->id],
                ['status' => 'complete', 'score' => 1.0],
            );

            return $submission;
        });

        $xapi->record($learner->id, XapiRecorder::VERB_RESPONDED, 'components', $component->id, $component->title ?? 'Assignment', XapiRecorder::ACTIVITY_INTERACTION);

        return response()->json(['data' => [
            'id' => $submission->id,
            'status' => $submission->parent_review_status,
            'coins_pending' => $submission->coins_locked,
        ]], 201);
    }
}
