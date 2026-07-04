<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Language;
use App\Models\Lesson;
use App\Models\LessonComponent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Seeder;

/**
 * Builds ONE complete, published Yorùbá course that samples the v1 lesson
 * activities — all self-graded, with no user recording or manual review:
 *   • component types: video, exercise (flashcards), game, quiz
 *   • all 4 game types: memory, match, tone_pop, word_builder
 *   • all 9 quiz question types: mcq_single, mcq_multi, true_false, fill_blank,
 *     listen_and_respond, complete_the_chat, word_bank, match_pairs, type_what_you_hear
 *
 * Deferred to v2 (require user recording + manual review): `speaking`, `assignment`.
 *
 * The write path mirrors LessonComponentController exactly (same relations +
 * detail tables) so the content renders and grades in the player/preview.
 *
 * Idempotent: re-running replaces the showcase course. Run with:
 *   php artisan db:seed --class="Database\Seeders\ShowcaseCourseSeeder"
 */
class ShowcaseCourseSeeder extends Seeder
{
    private const TITLE = 'Yorùbá Foundations — Activity Showcase';

    public function run(): void
    {
        $language = Language::firstOrCreate(['code' => 'yo'], ['name' => 'Yoruba']);
        $owner = User::role('content_owner')->first() ?? User::role('super_admin')->first();

        // Sample media, referenced by the video / audio / image activities.
        $video = MediaAsset::firstOrCreate(
            ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4'],
            ['type' => 'video', 'duration_seconds' => 15],
        );
        $audio = MediaAsset::firstOrCreate(
            ['url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3'],
            ['type' => 'audio', 'duration_seconds' => 6],
        );
        $image = MediaAsset::firstOrCreate(
            ['url' => 'https://picsum.photos/seed/mahadum-yoruba/640/400'],
            ['type' => 'image'],
        );

        $this->resetExisting();

        $course = Course::create([
            'language_id' => $language->id,
            'title' => self::TITLE,
            'description' => 'A demonstration course containing a sample of every lesson activity type available on MAHADUM.360 — video, speaking, flashcards, games, a full quiz, and a parent-reviewed assignment.',
            'level_band' => 'A1',
            'owner_user_id' => $owner?->id,
            'status' => 'published',
            'is_published' => true,
        ]);

        $level = CourseLevel::create([
            'course_id' => $course->id,
            'title' => 'Unit 1 — Greetings & Everyday Words',
            'position' => 1,
            'has_assessment' => false,
        ]);

        $this->lessonIntroVideo($level, $language->id, $video->id);
        $this->lessonFlashcardsAndGames($level, $audio->id, $image->id);
        $this->lessonMegaQuiz($level, $audio->id, $image->id);

        $this->command?->info('Showcase course seeded: "'.self::TITLE.'" (course #'.$course->id.').');
    }

    /** Lesson 1: an intro video. (Speaking needs recording + review — deferred to v2.) */
    private function lessonIntroVideo(CourseLevel $level, int $languageId, int $videoAssetId): void
    {
        $lesson = $this->lesson($level, 'Meet your teacher', 1);

        $video = $this->component($lesson, 'video', 1, 'Welcome video', 10, ['require_watch' => true]);
        $video->video()->create([
            'language_id' => $languageId,
            'title' => 'Ẹ káàbọ̀ — Welcome to Yorùbá',
            'presenter_name' => 'Adé',
            'duration_seconds' => 15,
            'default_quality' => '360p',
            'source_asset_id' => $videoAssetId,
            'status' => 'ready',
            'kind' => 'lesson',
        ]);
    }

    /** Lesson 2: a flashcard deck + all four game types. */
    private function lessonFlashcardsAndGames(CourseLevel $level, int $audioAssetId, int $imageAssetId): void
    {
        $lesson = $this->lesson($level, 'Flashcards & Games', 2);

        $exercise = $this->component($lesson, 'exercise', 1, 'Greetings flashcards', 10);
        $deck = $exercise->exercise()->create(['mode' => 'flashcards']);
        foreach ([
            ['front_text' => 'Good morning', 'back_text' => 'Ẹ káàrọ̀', 'mnemonic' => 'eh-KAA-raw', 'audio_asset_id' => $audioAssetId, 'image_asset_id' => $imageAssetId],
            ['front_text' => 'Good afternoon', 'back_text' => 'Ẹ káàsán', 'mnemonic' => 'eh-KAA-san'],
            ['front_text' => 'Thank you', 'back_text' => 'Ẹ ṣé', 'mnemonic' => 'eh-SHEH'],
        ] as $card) {
            $deck->flashcards()->create($card);
        }

        $games = [
            ['memory', 'Memory match', [['a' => 'Good morning', 'b' => 'Ẹ káàrọ̀'], ['a' => 'Thank you', 'b' => 'Ẹ ṣé']]],
            ['match', 'Match the numbers', [['a' => 'One', 'b' => 'Ọ̀kan'], ['a' => 'Two', 'b' => 'Èjì'], ['a' => 'Three', 'b' => 'Ẹ̀ta']]],
            ['tone_pop', 'Tone pop', [['a' => 'High tone', 'b' => 'á'], ['a' => 'Mid tone', 'b' => 'a'], ['a' => 'Low tone', 'b' => 'à']]],
            ['word_builder', 'Build the word', [['a' => 'Ẹ + káàrọ̀', 'b' => 'Ẹ káàrọ̀']]],
        ];
        foreach ($games as $i => [$type, $title, $pairs]) {
            $component = $this->component($lesson, 'game', $i + 2, $title, 10);
            $component->game()->create(['game_type' => $type, 'config' => ['pairs' => $pairs]]);
        }
    }

    /** Lesson 3: one quiz carrying every question type. */
    private function lessonMegaQuiz(CourseLevel $level, int $audioAssetId, int $imageAssetId): void
    {
        $lesson = $this->lesson($level, 'Mega Quiz — every question type', 3);
        $component = $this->component($lesson, 'quiz', 1, 'Show what you know', 0);

        $quiz = $component->quiz()->create([
            'title' => 'Everything quiz',
            'pass_threshold' => 0.6,
            'shuffle_questions' => false,
            'max_attempts' => 3,
            'hearts_enabled' => true,
        ]);

        // Each entry: [type, prompt, options[], extra fields]. Options are
        // [label, is_correct, match_target?]. word_bank options are in the
        // correct order; match_pairs uses match_target; type_what_you_hear has
        // no options and grades against target_text.
        $questions = [
            ['mcq_single', 'How do you say “Hello” in Yorùbá?', [
                ['Ẹ n lẹ́', true], ['Ó dàbọ̀', false], ['Ẹ ṣé', false], ['Omi', false],
            ], ['explanation' => '“Ẹ n lẹ́” is a common greeting.', 'prompt_image_asset_id' => $imageAssetId]],

            ['mcq_multi', 'Which of these are greetings? (choose all that apply)', [
                ['Ẹ káàrọ̀', true], ['Ẹ káàsán', true], ['Omi', false], ['Oúnjẹ', false],
            ], ['explanation' => 'Ẹ káàrọ̀ and Ẹ káàsán are greetings; the others are nouns.']],

            ['true_false', '“Ẹ ṣé” means “Thank you”.', [
                ['True', true], ['False', false],
            ], ['explanation' => 'Correct — “Ẹ ṣé” means thank you.']],

            ['fill_blank', 'Fill the blank: “____” means “Good morning”.', [
                ['Ẹ káàrọ̀', true], ['Ẹ káàsán', false], ['Ẹ ṣé', false],
            ], ['explanation' => 'Ẹ káàrọ̀ = Good morning.']],

            ['listen_and_respond', 'Listen, then choose the phrase you heard.', [
                ['Ẹ káàrọ̀', true], ['Ó dàbọ̀', false], ['Omi', false],
            ], ['explanation' => 'You heard the morning greeting.', 'prompt_audio_asset_id' => $audioAssetId]],

            ['complete_the_chat', 'Bọ́lá says: “Ẹ n lẹ́!”  You reply:', [
                ['Ẹ n lẹ́', true], ['Oúnjẹ', false], ['Ilé', false],
            ], ['explanation' => 'Return the greeting.']],

            // word_bank: tiles are stored in the CORRECT order; the player shuffles them.
            ['word_bank', 'Arrange the tiles to say “Good afternoon”.', [
                ['Ẹ', false], ['káàsán', false],
            ], ['explanation' => '“Ẹ káàsán” = Good afternoon.']],

            // match_pairs: each option (left) carries its match_target (the pool the
            // learner picks from). Reinforces the greetings taught by the flashcards.
            ['match_pairs', 'Match each Yorùbá greeting to its meaning.', [
                ['Ẹ káàrọ̀', false, 'Good morning'], ['Ẹ káàsán', false, 'Good afternoon'], ['Ẹ ṣé', false, 'Thank you'],
            ], ['explanation' => 'Ẹ káàrọ̀ = Good morning, Ẹ káàsán = Good afternoon, Ẹ ṣé = Thank you.']],

            // type_what_you_hear: free text, graded against target_text.
            ['type_what_you_hear', 'Type what you hear.', [], [
                'explanation' => 'You heard “Ẹ ṣé”.',
                'target_text' => 'Ẹ ṣé',
                'prompt_audio_asset_id' => $audioAssetId,
            ]],
        ];

        foreach ($questions as $i => [$type, $prompt, $options, $extra]) {
            $question = $quiz->questions()->create([
                'type' => $type,
                'prompt' => $prompt,
                'explanation' => $extra['explanation'] ?? null,
                'target_text' => $extra['target_text'] ?? null,
                'prompt_audio_asset_id' => $extra['prompt_audio_asset_id'] ?? null,
                'prompt_image_asset_id' => $extra['prompt_image_asset_id'] ?? null,
                'points' => 1,
                'position' => $i + 1,
            ]);

            foreach ($options as $j => $option) {
                $question->options()->create([
                    'label' => $option[0],
                    'is_correct' => $option[1] ?? false,
                    'match_target' => $option[2] ?? null,
                    'position' => $j + 1,
                ]);
            }
        }
    }

    // ---- helpers -----------------------------------------------------------

    private function lesson(CourseLevel $level, string $title, int $position): Lesson
    {
        return Lesson::create([
            'course_level_id' => $level->id,
            'title' => $title,
            'position' => $position,
            'est_minutes' => 5,
            'is_locked_by_default' => false, // showcase: every lesson open to preview
            'version' => 1,
            'published_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    private function component(Lesson $lesson, string $type, int $position, string $title, int $xp, ?array $settings = null): LessonComponent
    {
        return $lesson->components()->create([
            'type' => $type,
            'position' => $position,
            'title' => $title,
            'is_required' => true,
            'xp_value' => $xp,
            'settings' => $settings,
        ]);
    }

    /** Remove any prior showcase course (and its detached video rows) for a clean reseed. */
    private function resetExisting(): void
    {
        $courses = Course::withTrashed()->where('title', self::TITLE)->get();
        foreach ($courses as $course) {
            $levelIds = CourseLevel::where('course_id', $course->id)->pluck('id');
            $lessonIds = Lesson::withTrashed()->whereIn('course_level_id', $levelIds)->pluck('id');
            $componentIds = LessonComponent::whereIn('lesson_id', $lessonIds)->pluck('id');
            // videos use nullOnDelete, so they'd orphan on cascade — remove them first.
            Video::whereIn('lesson_component_id', $componentIds)->delete();
            $course->forceDelete(); // cascades levels → lessons → components → typed detail
        }
    }
}
