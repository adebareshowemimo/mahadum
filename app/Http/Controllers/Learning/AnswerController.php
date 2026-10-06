<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreAnswerRequest;
use App\Models\ComponentProgress;
use App\Models\LearnerProfile;
use App\Models\LessonComponent;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\Quiz;
use App\Models\QuizAnswerReceipt;
use App\Models\QuizAttempt;
use App\Models\XpLedger;
use App\Services\Billing\EntitlementResolver;
use App\Services\Gamification\LearningLevelService;
use App\Services\Gamification\PracticeModeService;
use App\Services\Learning\AnswerGrader;
use App\Services\Learning\XapiRecorder;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnswerController extends Controller
{
    use ResolvesLearner;

    public function store(StoreAnswerRequest $request, LessonComponent $component, AnswerGrader $grader, XapiRecorder $xapi, ReferralService $referrals, EntitlementResolver $entitlements, PracticeModeService $practice, LearningLevelService $levels): JsonResponse
    {
        abort_unless($component->type === 'quiz', 422, 'This component is not a quiz.');

        $learner = $this->learner($request->integer('learner_id'));
        $quiz = $component->quiz()->with('questions.options')->firstOrFail();
        $question = $quiz->questions->firstWhere('id', $request->integer('question_id'));

        abort_if($question === null, 422, 'Question does not belong to this component.');

        $verdict = $grader->grade($question, $request->array('answer'));

        $unlimitedHearts = (bool) $entitlements->forLearner($learner)['unlimited_hearts'];

        return DB::transaction(function () use ($request, $learner, $component, $quiz, $question, $verdict, $xapi, $referrals, $unlimitedHearts, $practice, $levels) {
            LearnerProfile::whereKey($learner->id)->lockForUpdate()->firstOrFail();
            $progress = $this->lessonProgress($learner, $component->lesson);

            $requestId = (string) $request->input('request_id');
            $fingerprint = hash('sha256', json_encode([
                'component_id' => $component->id,
                'question_id' => $question->id,
                'answer' => $request->array('answer'),
                'time_ms' => $request->integer('time_ms'),
            ], JSON_THROW_ON_ERROR));
            $receipt = QuizAnswerReceipt::where('learner_profile_id', $learner->id)
                ->where('request_id', $requestId)->first();

            // Durable replay protection must precede opening the next attempt.
            // Learner authorization and current lesson/hearts access still apply.
            if ($receipt !== null) {
                if (! hash_equals($receipt->fingerprint, $fingerprint)) {
                    return response()->json(['error' => [
                        'code' => 'answer_request_conflict',
                        'message' => 'This answer request ID has already been used for a different submission.',
                    ]], 409);
                }

                return response()->json(['data' => $receipt->response])->header('Idempotency-Replayed', 'true');
            }

            $attempt = $this->resolveAttempt($learner->id, $quiz);

            // Attempt cap reached (a replay past `max_attempts`): grade for practice
            // so the learner still sees the answer + explanation — learning is never
            // dead-ended (Rule 4) — but nothing is scored and no XP/hearts move.
            if ($attempt === null) {
                $heartState = $unlimitedHearts
                    ? ['current' => null, 'practice_mode' => false, 'competitive_paused_until' => null]
                    : $practice->applyMistake($learner, false);

                return $this->recordAnswer($learner->id, $requestId, $fingerprint, null, [
                    'correct' => $verdict['is_correct'],
                    'correct_answer' => $verdict['correct_answer'],
                    'explanation' => $verdict['explanation'],
                    'hearts_remaining' => $heartState['current'],
                    'unlimited_hearts' => $unlimitedHearts,
                    'practice_mode' => $heartState['practice_mode'],
                    'competitive_paused_until' => $heartState['competitive_paused_until'],
                    'xp_awarded' => 0,
                    'attempts_exhausted' => true,
                ]);
            }

            $existingResponse = QuestionResponse::where('quiz_attempt_id', $attempt->id)->where('question_id', $question->id)->first();
            $beforeHearts = $unlimitedHearts ? null : $practice->state($learner)['current'];
            $countAnswer = ! $unlimitedHearts && $quiz->hearts_enabled && $existingResponse === null;
            $heartState = $unlimitedHearts
                ? ['current' => null, 'practice_mode' => false, 'competitive_paused_until' => null]
                : $practice->applyMistake($learner, $countAnswer);
            $heartsLost = $unlimitedHearts ? 0 : max(0, $beforeHearts - $heartState['current']);

            // Each permitted attempt earns one XP per correct question, including
            // retries. Corrections/repeated answers inside that attempt earn once.
            $alreadyEarned = ($existingResponse->xp_awarded ?? 0) > 0;

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
                $xpAwarded = 1;
                XpLedger::create([
                    'learner_profile_id' => $learner->id,
                    'amount' => $xpAwarded,
                    'source' => 'quiz',
                    'reference_type' => Question::class,
                    'reference_id' => $question->id,
                ]);
                $levels->forLearner($learner);
            }

            QuestionResponse::where('quiz_attempt_id', $attempt->id)->where('question_id', $question->id)
                ->update(['xp_awarded' => ($existingResponse->xp_awarded ?? 0) + $xpAwarded]);

            $this->syncQuizProgress($progress->id, $component, $quiz, $learner->id, $attempt);

            // A finished quiz may complete a referral's activation gate (FR-7).
            if ($attempt->completed_at !== null) {
                $referrals->maybeActivateForLearner($learner);
            }

            $xapi->record($learner->id, XapiRecorder::VERB_ANSWERED, 'questions', $question->id, $question->prompt, XapiRecorder::ACTIVITY_INTERACTION, [
                'success' => $verdict['is_correct'],
                'score' => ['scaled' => $verdict['is_correct'] ? 1.0 : 0.0],
            ]);

            return $this->recordAnswer($learner->id, $requestId, $fingerprint, $attempt->id, [
                'correct' => $verdict['is_correct'],
                'correct_answer' => $verdict['correct_answer'],
                'explanation' => $verdict['explanation'],
                'hearts_remaining' => $heartState['current'],
                'unlimited_hearts' => $unlimitedHearts,
                'practice_mode' => $heartState['practice_mode'],
                'competitive_paused_until' => $heartState['competitive_paused_until'],
                'xp_awarded' => $xpAwarded,
                'attempts_exhausted' => false,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function recordAnswer(int $learnerId, string $requestId, string $fingerprint, ?int $attemptId, array $data): JsonResponse
    {
        QuizAnswerReceipt::create([
            'learner_profile_id' => $learnerId,
            'request_id' => $requestId,
            'fingerprint' => $fingerprint,
            'quiz_attempt_id' => $attemptId,
            'response' => $data,
        ]);

        return response()->json(['data' => $data]);
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
