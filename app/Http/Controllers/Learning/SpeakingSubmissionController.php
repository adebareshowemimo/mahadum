<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreSpeakingSubmissionRequest;
use App\Models\ComponentProgress;
use App\Models\LessonComponent;
use App\Models\MediaAsset;
use App\Models\SpeakingSubmission;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SpeakingSubmissionController extends Controller
{
    use ResolvesLearner;

    /**
     * Store a speaking recording. AI scoring is deferred (Option B) — the
     * submission ships as `needs_review` for parent/teacher review, and the
     * speaking component is marked complete so the lesson can be finished.
     */
    public function store(StoreSpeakingSubmissionRequest $request, XapiRecorder $xapi): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        $component = LessonComponent::findOrFail($request->integer('component_id'));

        abort_unless($component->type === 'speaking', 422, 'This component is not a speaking challenge.');

        $submission = DB::transaction(function () use ($request, $learner, $component) {
            $assetId = null;
            if ($request->hasFile('audio')) {
                $path = $request->file('audio')->store('speaking', 'public');
                $assetId = MediaAsset::create([
                    'type' => 'audio',
                    'url' => $path,
                    'uploaded_by' => $request->user()->id,
                ])->id;
            }

            $submission = SpeakingSubmission::create([
                'learner_profile_id' => $learner->id,
                'lesson_component_id' => $component->id,
                'audio_asset_id' => $assetId,
                'status' => 'needs_review',
            ]);

            $progress = $this->lessonProgress($learner, $component->lesson);
            ComponentProgress::updateOrCreate(
                ['lesson_progress_id' => $progress->id, 'lesson_component_id' => $component->id],
                ['status' => 'complete', 'score' => 1.0],
            );

            return $submission;
        });

        $xapi->record($learner->id, XapiRecorder::VERB_RESPONDED, 'components', $component->id, $component->title ?? 'Speaking', XapiRecorder::ACTIVITY_INTERACTION);

        return response()->json(['data' => ['id' => $submission->id, 'status' => $submission->status]], 201);
    }
}
