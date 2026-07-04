<?php

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\Family;
use App\Models\Language;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\User;

trait MakesContent
{
    /**
     * Build a published lesson with the three required components
     * (video ready, quiz with one correct option, speaking).
     */
    protected function publishedLesson(): Lesson
    {
        $language = Language::firstOrCreate(['code' => 'ig'], ['name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);

        $course = Course::create([
            'language_id' => $language->id, 'title' => 'Test Course',
            'status' => 'published', 'is_published' => true,
        ]);
        $level = $course->levels()->create(['title' => 'L1', 'position' => 1]);
        $lesson = $level->lessons()->create(['title' => 'Lesson A', 'position' => 1]);

        $video = $lesson->components()->create(['type' => 'video', 'position' => 1, 'xp_value' => 5]);
        $video->video()->create(['title' => 'V', 'duration_seconds' => 10, 'status' => 'ready', 'kind' => 'lesson']);

        $quizC = $lesson->components()->create(['type' => 'quiz', 'position' => 2, 'xp_value' => 10]);
        $quiz = $quizC->quiz()->create(['pass_threshold' => 0.5]);
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Pick', 'points' => 2, 'position' => 1]);
        $question->options()->create(['label' => 'Right', 'is_correct' => true, 'position' => 1]);
        $question->options()->create(['label' => 'Wrong', 'is_correct' => false, 'position' => 2]);

        $speaking = $lesson->components()->create(['type' => 'speaking', 'position' => 3, 'xp_value' => 8]);
        $speaking->speakingPrompt()->create(['prompt_text' => 'Say', 'target_text' => 'x']);

        $lesson->update(['published_at' => now(), 'is_locked_by_default' => false]);

        return $lesson->fresh(['components.video', 'components.quiz.questions.options', 'components.speakingPrompt']);
    }

    /** A parent + a child learner in their family. */
    protected function parentWithChild(User $parent): LearnerProfile
    {
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);

        return LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Kid', 'current_level' => 1]);
    }

    protected function componentOfType(Lesson $lesson, string $type)
    {
        return $lesson->components->firstWhere('type', $type);
    }
}
