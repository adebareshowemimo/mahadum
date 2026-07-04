<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreAssessmentRequest;
use App\Models\PlacementAssessment;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;

class AssessmentController extends Controller
{
    use ResolvesLearner;

    private const LEVELS = ['A1' => 1, 'A2' => 2, 'B1' => 3];

    public function store(StoreAssessmentRequest $request, XapiRecorder $xapi): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));

        $score = $request->float('score', 0.0);
        $level = $this->resultLevel($score);

        $assessment = PlacementAssessment::create([
            'learner_profile_id' => $learner->id,
            'language_id' => $request->integer('language_id'),
            'result_level' => $level,
            'answers' => $request->input('answers'),
            'completed_at' => now(),
        ]);

        $learner->update([
            'target_language_id' => $request->integer('language_id'),
            'current_level' => self::LEVELS[$level],
        ]);

        $xapi->record($learner->id, XapiRecorder::VERB_COMPLETED, 'assessments', $assessment->id, 'Placement assessment', XapiRecorder::ACTIVITY_ASSESSMENT, [
            'completion' => true,
            'score' => ['scaled' => round($score, 4)],
            'extensions' => [$xapi->iri('ext', 'level') => $level],
        ]);

        return response()->json(['data' => ['result_level' => $level]]);
    }

    private function resultLevel(float $score): string
    {
        return match (true) {
            $score >= 0.8 => 'B1',
            $score >= 0.5 => 'A2',
            default => 'A1',
        };
    }
}
