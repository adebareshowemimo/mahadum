<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreAssessmentRequest;
use App\Models\PlacementAssessment;
use App\Services\Learning\PlacementQuestionBank;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssessmentController extends Controller
{
    use ResolvesLearner;

    public function questions(Request $request, PlacementQuestionBank $bank): JsonResponse
    {
        $values = $request->validate(['learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'language_id' => ['required', 'integer', 'exists:languages,id']]);
        $learner = $this->learnerForEnrollment((int) $values['learner_id']);
        $prior = PlacementAssessment::where('learner_profile_id', $learner->id)
            ->where('language_id', $values['language_id'])->latest('id')->first();
        $questions = $prior ? collect() : $bank->forLanguage((int) $values['language_id']);

        return response()->json(['data' => [
            'available' => $questions->count() === 4,
            'result_level' => $prior?->result_level,
            'questions' => $questions->map(fn ($question) => [
                'id' => $question->id, 'prompt' => $question->prompt,
                'prompt_audio' => $question->promptAudioAsset ? Storage::disk('public')->url($question->promptAudioAsset->url) : null,
                'options' => $question->options->map(fn ($option) => ['id' => $option->id, 'label' => $option->label])->values(),
            ])->values(),
        ]]);
    }

    public function store(StoreAssessmentRequest $request, XapiRecorder $xapi, PlacementQuestionBank $bank): JsonResponse
    {
        $learner = $this->learnerForEnrollment($request->integer('learner_id'));
        abort_if(PlacementAssessment::where('learner_profile_id', $learner->id)
            ->where('language_id', $request->integer('language_id'))->exists(), 422, 'Placement has already been completed for this language.');
        $questions = $bank->forLanguage($request->integer('language_id'));
        abort_unless($questions->count() === 4, 422, 'A placement test is not ready for this language. Start at Beginner.');
        $answers = collect($request->input('answers'))->keyBy('question_id');
        abort_unless($answers->keys()->sort()->values()->all() === $questions->pluck('id')->sort()->values()->all(),
            422, 'Answer each placement question once.');
        $correct = 0;
        foreach ($questions as $question) {
            $option = $question->options->firstWhere('id', $answers[$question->id]['option_id']);
            abort_unless($option !== null, 422, 'Choose an answer shown for each question.');
            $correct += (int) $option->is_correct;
        }
        $score = $correct / 4;
        $level = $score >= 0.75 ? 'Intermediate' : 'Beginner';

        $assessment = PlacementAssessment::create([
            'learner_profile_id' => $learner->id,
            'language_id' => $request->integer('language_id'),
            'result_level' => $level,
            'answers' => $request->input('answers'),
            'completed_at' => now(),
        ]);

        $learner->update([
            'target_language_id' => $request->integer('language_id'),
        ]);

        $xapi->record($learner->id, XapiRecorder::VERB_COMPLETED, 'assessments', $assessment->id, 'Placement assessment', XapiRecorder::ACTIVITY_ASSESSMENT, [
            'completion' => true,
            'score' => ['scaled' => round($score, 4)],
            'extensions' => [$xapi->iri('ext', 'level') => $level],
        ]);

        return response()->json(['data' => ['result_level' => $level, 'score' => $score,
            'starting_level_position' => $learner->starting_level_position ?? ($level === 'Intermediate' ? 2 : 0)]]);
    }
}
