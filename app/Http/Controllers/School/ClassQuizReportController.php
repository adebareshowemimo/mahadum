<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\QuestionResponse;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;

class ClassQuizReportController extends Controller
{
    private function quizIds(SchoolClass $class)
    {
        $courseIds = $class->courseAssignments()->pluck('course_id');

        return Quiz::whereHas('lessonComponent.lesson.courseLevel', fn ($level) => $level->whereIn('course_id', $courseIds))
            ->whereHas('lessonComponent.lesson', fn ($lesson) => $lesson->whereNotNull('published_at'))
            ->pluck('id');
    }

    public function index(SchoolClass $class): JsonResponse
    {
        $learnerIds = $class->currentEnrollments()->pluck('learner_profile_id');
        $quizzes = Quiz::whereIn('id', $this->quizIds($class))
            ->with(['lessonComponent.lesson', 'questions.responses' => fn ($q) => $q->whereIn('learner_profile_id', $learnerIds)])
            ->get();
        $attempts = QuizAttempt::whereIn('quiz_id', $quizzes->pluck('id'))
            ->whereIn('learner_profile_id', $learnerIds)->whereNotNull('completed_at')->get()->groupBy('quiz_id');

        return response()->json(['data' => $quizzes->map(function (Quiz $quiz) use ($attempts) {
            $finished = $attempts->get($quiz->id, collect());

            return ['quiz_id' => $quiz->id, 'title' => $quiz->title ?: $quiz->lessonComponent->lesson->title,
                'lesson_id' => $quiz->lessonComponent->lesson_id,
                'attempts' => $finished->count(),
                'average_score' => $finished->isEmpty() ? null : (int) round($finished->avg('score') * 100),
                'most_missed' => $quiz->questions->map(fn ($question) => [
                    'question_id' => $question->id, 'prompt' => $question->prompt,
                    'wrong_count' => $question->responses->where('is_correct', false)->count(),
                ])->sortByDesc('wrong_count')->take(3)->values()->all(),
            ];
        })->values()]);
    }

    public function show(SchoolClass $class, QuizAttempt $attempt): JsonResponse
    {
        abort_unless($class->currentEnrollments()->where('learner_profile_id', $attempt->learner_profile_id)->exists()
            && $this->quizIds($class)->contains($attempt->quiz_id), 404);
        $attempt->load(['learnerProfile', 'quiz.lessonComponent.lesson', 'quiz.questions.options']);
        $responses = QuestionResponse::where('quiz_attempt_id', $attempt->id)->get()->keyBy('question_id');

        return response()->json(['data' => [
            'id' => $attempt->id, 'learner' => $attempt->learnerProfile?->display_name,
            'quiz_title' => $attempt->quiz->title ?: $attempt->quiz->lessonComponent->lesson->title,
            'score' => $attempt->score === null ? null : (int) round((float) $attempt->score * 100),
            'completed_at' => $attempt->completed_at,
            'questions' => $attempt->quiz->questions->sortBy('position')->map(function ($question) use ($responses) {
                $response = $responses->get($question->id);
                $given = $response?->given_answer;
                $answerText = $response === null ? null : (isset($given['option_id'])
                    ? $question->options->firstWhere('id', $given['option_id'])?->label
                    : (isset($given['option_ids'])
                        ? $question->options->whereIn('id', $given['option_ids'])->pluck('label')->join(', ')
                        : (isset($given['text']) ? (string) $given['text'] : json_encode($given))));

                return ['id' => $question->id, 'prompt' => $question->prompt,
                    'given_answer' => $answerText, 'is_correct' => $response?->is_correct,
                    'correct_options' => $question->options->where('is_correct', true)->pluck('label')->values(),
                ];
            })->values(),
        ]]);
    }
}
