<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreAnswerRequest;
use App\Models\ComponentProgress;
use App\Models\Heart;
use App\Models\LessonComponent;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\XpLedger;
use App\Services\Learning\AnswerGrader;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnswerController extends Controller
{
    use ResolvesLearner;

    public function store(StoreAnswerRequest $request, LessonComponent $component, AnswerGrader $grader, XapiRecorder $xapi): JsonResponse
    {
        abort_unless($component->type === 'quiz', 422, 'This component is not a quiz.');

        $learner = $this->learner($request->integer('learner_id'));
        $quiz = $component->quiz()->with('questions.options')->firstOrFail();
        $question = $quiz->questions->firstWhere('id', $request->integer('question_id'));

        abort_if($question === null, 422, 'Question does not belong to this component.');

        $verdict = $grader->grade($question, $request->array('answer'));

        return DB::transaction(function () use ($request, $learner, $component, $quiz, $question, $verdict, $xapi) {
            $progress = $this->lessonProgress($learner, $component->lesson);

            $attempt = $this->resolveAttempt($learner->id, $quiz);

            // Attempt cap reached (a replay past `max_attempts`): grade for practice
            // so the learner still sees the answer + explanation — learning is never
            // dead-ended (Rule 4) — but nothing is scored and no XP/hearts move.
            if ($attempt === null) {
                return response()->json(['data' => [
                    'correct' => $verdict['is_correct'],
                    'correct_answer' => $verdict['correct_answer'],
                    'explanation' => $verdict['explanation'],
                    'hearts_remaining' => $this->applyHearts($learner->id, 0),
                    'xp_awarded' => 0,
                    'attempts_exhausted' => true,
                ]]);
            }

            $heartsLost = (! $verdict['is_correct'] && $quiz->hearts_enabled) ? 1 : 0;
            $heartsRemaining = $this->applyHearts($learner->id, $heartsLost);

            // XP for a question is earned once per learner, never re-farmed on replay.
            $alreadyEarned = QuestionResponse::where('learner_profile_id', $learner->id)
                ->where('question_id', $question->id)
                ->where('is_correct', true)
                ->exists();

            QuestionResponse::updateOrCreate(
                ['learner_profile_id' => $learner->id, 'question_id' => $question->id, 'quiz_attempt_id' => $attempt->id],
                [
                    'given_answer' => $request->array('answer'),
                    'is_correct' => $verdict['is_correct'],
                    'time_ms' => $request->integer('time_ms'),
                    'hearts_lost' => $heartsLost,
                    'answered_at' => now(),
                ],
            );

            $xpAwarded = 0;
            if ($verdict['is_correct'] && ! $alreadyEarned) {
                $xpAwarded = (int) $question->points;
                XpLedger::create([
                    'learner_profile_id' => $learner->id,
                    'amount' => $xpAwarded,
                    'source' => 'quiz',
                    'reference_type' => Question::class,
                    'reference_id' => $question->id,
                ]);
            }

            $this->syncQuizProgress($progress->id, $component, $quiz, $learner->id, $attempt);

            $xapi->record($learner->id, XapiRecorder::VERB_ANSWERED, 'questions', $question->id, $question->prompt, XapiRecorder::ACTIVITY_INTERACTION, [
                'success' => $verdict['is_correct'],
                'score' => ['scaled' => $verdict['is_correct'] ? 1.0 : 0.0],
            ]);

            return response()->json(['data' => [
                'correct' => $verdict['is_correct'],
                'correct_answer' => $verdict['correct_answer'],
                'explanation' => $verdict['explanation'],
                'hearts_remaining' => $heartsRemaining,
                'xp_awarded' => $xpAwarded,
                'attempts_exhausted' => false,
            ]]);
        });
    }

    /**
     * The quiz attempt to record this answer against: the learner's in-progress
     * attempt if one exists, otherwise a fresh attempt — unless `max_attempts` is
     * set and every allowed attempt is already complete, in which case null
     * signals "practice mode" (grade + feedback only, nothing scored).
     */
    private function resolveAttempt(int $learnerId, Quiz $quiz): ?QuizAttempt
    {
        $inProgress = QuizAttempt::where('learner_profile_id', $learnerId)
            ->where('quiz_id', $quiz->id)
            ->whereNull('completed_at')
            ->orderByDesc('attempt_no')
            ->first();

        if ($inProgress !== null) {
            return $inProgress;
        }

        if ($quiz->max_attempts !== null) {
            $completed = QuizAttempt::where('learner_profile_id', $learnerId)
                ->where('quiz_id', $quiz->id)
                ->whereNotNull('completed_at')
                ->count();

            if ($completed >= $quiz->max_attempts) {
                return null;
            }
        }

        $nextNo = (int) QuizAttempt::where('learner_profile_id', $learnerId)
            ->where('quiz_id', $quiz->id)
            ->max('attempt_no') + 1;

        return QuizAttempt::create([
            'learner_profile_id' => $learnerId,
            'quiz_id' => $quiz->id,
            'attempt_no' => $nextNo,
            'started_at' => now(),
        ]);
    }

    /** Decrement hearts but never below 0 (Rule 4 — never blocks learning). */
    private function applyHearts(int $learnerId, int $lost): int
    {
        $heart = Heart::firstOrCreate(['learner_profile_id' => $learnerId], ['current' => 5]);

        if ($lost > 0) {
            $heart->current = max(0, $heart->current - $lost);
            $heart->save();
        }

        return $heart->current;
    }

    private function syncQuizProgress(int $lessonProgressId, LessonComponent $component, $quiz, int $learnerId, QuizAttempt $attempt): void
    {
        $total = $quiz->questions->count();
        $responses = QuestionResponse::where('quiz_attempt_id', $attempt->id)->get();
        $answered = $responses->count();
        $correct = $responses->where('is_correct', true)->count();
        $ratio = $total > 0 ? round($correct / $total, 4) : 0.0;

        $complete = $answered >= $total;

        ComponentProgress::updateOrCreate(
            ['lesson_progress_id' => $lessonProgressId, 'lesson_component_id' => $component->id],
            [
                'status' => $complete ? 'complete' : 'in_progress',
                'score' => $ratio,
                'attempts' => $answered,
                'data' => ['answered' => $answered, 'total' => $total, 'correct' => $correct],
            ],
        );

        if ($complete) {
            $attempt->update([
                'score' => $ratio,
                'passed' => $ratio >= (float) $quiz->pass_threshold,
                'completed_at' => now(),
            ]);
        }
    }
}
