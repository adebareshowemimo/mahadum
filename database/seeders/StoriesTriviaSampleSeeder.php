<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Language;
use App\Models\Lesson;
use App\Models\LessonComponent;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Published, clearly labelled examples for the separate creator and learner libraries.
 * Run on a local development database with: php artisan db:seed --class=StoriesTriviaSampleSeeder
 */
class StoriesTriviaSampleSeeder extends Seeder
{
    private const COURSE_TITLE = '[Sample] Yoruba Stories & Wisdom';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Sample content cannot be seeded in production.');
        }

        $owner = User::role('content_owner')->first() ?? User::role('super_admin')->first();
        if (! $owner) {
            throw new \RuntimeException('Seed a content owner or super admin before sample content.');
        }

        $language = Language::firstOrCreate(['code' => 'yo'], ['name' => 'Yoruba', 'is_active' => true]);
        $course = Course::firstOrCreate(['title' => self::COURSE_TITLE], [
            'language_id' => $language->id,
            'owner_user_id' => $owner->id,
            'description' => 'Published sample lessons and stories for testing the creator and family libraries.',
            'level_band' => 'A1',
            'status' => 'published',
            'is_published' => true,
        ]);
        $level = CourseLevel::firstOrCreate(['course_id' => $course->id, 'position' => 1], [
            'title' => 'Wisdom and folklore', 'is_free' => true, 'has_assessment' => false,
        ]);

        $this->proverbLesson($level);
        $this->triviaLesson($level);
        $this->colouringStory($course);
        $this->tracingStory($course);
        $this->animatedStory($course);
        $this->readingStories($course);

        $this->command->info('Seeded 2 lessons and 6 published stories in "'.self::COURSE_TITLE.'". Re-running adds no duplicates.');
    }

    private function proverbLesson(CourseLevel $level): void
    {
        $lesson = $this->lesson($level, '[Sample Proverb] Wisdom in everyday words', 'proverb', 1);
        $cards = $this->component($lesson, 'exercise', 1, 'Three Yoruba proverbs', 10);
        $deck = $cards->exercise()->firstOrCreate([], ['mode' => 'flashcards']);
        foreach ([
            ['Ọgbọ́n ju agbára lọ.', 'Wisdom is greater than strength.', 'A thoughtful choice can achieve more than force.'],
            ['Ìwà l’ẹwà.', 'Character is beauty.', 'How we treat people matters more than appearance.'],
            ['Sùúrù ni baba ìwà.', 'Patience is the foundation of good character.', 'Slow down, listen, and respond with care.'],
        ] as $card) {
            $deck->flashcards()->firstOrCreate(['front_text' => $card[0]], [
                'back_text' => $card[1], 'mnemonic' => $card[2],
            ]);
        }

        $quiz = $this->quiz($lesson, 2, 'Proverb meanings', 0, 2, false, 5);
        $this->question($quiz, 1, 'What does “Ìwà l’ẹwà” teach?', [
            ['Good character is beautiful.', true], ['Strength is everything.', false], ['Never ask for help.', false],
        ], 'The proverb values character over appearance.');
        $this->question($quiz, 2, 'Which proverb reminds us to be patient?', [
            ['Sùúrù ni baba ìwà.', true], ['Ọgbọ́n ju agbára lọ.', false], ['Ìwà l’ẹwà.', false],
        ], 'Sùúrù means patience.');
    }

    private function triviaLesson(CourseLevel $level): void
    {
        $lesson = $this->lesson($level, '[Sample Trivia] Yoruba language and culture', 'trivia', 2);
        $quiz = $this->quiz($lesson, 1, 'Quick culture challenge', 15, 3, true, 7);
        foreach ([
            ['What does “ẹ ṣé” mean?', [['Thank you', true], ['Good night', false], ['Water', false]], 'Ẹ ṣé is a way to say thank you.'],
            ['Which word means water?', [['Omi', true], ['Ilé', false], ['Ọ̀rẹ́', false]], 'Omi means water.'],
            ['What is a bàtá?', [['A drum', true], ['A market', false], ['A river', false]], 'Bàtá is a traditional drum.'],
            ['Which greeting is used in the morning?', [['Ẹ káàrọ̀', true], ['Ẹ káalẹ́', false], ['Ó dàbọ̀', false]], 'Ẹ káàrọ̀ means good morning.'],
            ['What does “ìtàn” mean?', [['Story', true], ['Food', false], ['School', false]], 'Ìtàn means story or history.'],
        ] as $index => $item) {
            $this->question($quiz, $index + 1, $item[0], $item[1], $item[2]);
        }
    }

    private function lesson(CourseLevel $level, string $title, string $kind, int $position): Lesson
    {
        return $level->lessons()->firstOrCreate(['title' => $title], [
            'kind' => $kind, 'position' => $position, 'est_minutes' => 5,
            'is_free_preview' => true, 'is_locked_by_default' => false,
            'version' => 1, 'published_at' => now(),
        ]);
    }

    private function component(Lesson $lesson, string $type, int $position, string $title, int $xp): LessonComponent
    {
        return $lesson->components()->firstOrCreate(['position' => $position], [
            'type' => $type, 'title' => $title, 'is_required' => true, 'xp_value' => $xp,
        ]);
    }

    private function quiz(Lesson $lesson, int $position, string $title, int $seconds, int $attempts, bool $hearts, int $xp)
    {
        $component = $this->component($lesson, 'quiz', $position, $title, 0);

        return $component->quiz()->firstOrCreate([], [
            'title' => $title, 'pass_threshold' => 0.6, 'shuffle_questions' => true,
            'max_attempts' => $attempts, 'hearts_enabled' => $hearts,
            'time_limit_seconds' => $seconds, 'xp_per_correct' => $xp,
        ]);
    }

    private function question($quiz, int $position, string $prompt, array $choices, string $explanation): void
    {
        $question = $quiz->questions()->firstOrCreate(['position' => $position], [
            'type' => 'mcq_single', 'prompt' => $prompt, 'explanation' => $explanation, 'points' => 1,
        ]);
        foreach ($choices as $index => [$label, $correct]) {
            $question->options()->firstOrCreate(['position' => $index + 1], [
                'label' => $label, 'is_correct' => $correct,
            ]);
        }
    }

    private function colouringStory(Course $course): void
    {
        $story = $this->story($course, '[Sample Colouring] Colours at the market', '0_5', 'colouring',
            'Follow a child through a Yoruba market. Colour one friendly object on each page.', '#f59e0b');
        $objects = ['Sun', 'Tomato', 'Basket', 'Orange', 'Leaf', 'Drum', 'Pot', 'Fish', 'Bird', 'Flower', 'Moon', 'Star', 'Home'];
        foreach ($objects as $index => $object) {
            $position = $index + 1;
            $story->pages()->firstOrCreate(['position' => $position], [
                'title' => $object, 'body' => "Colour the {$object}. What colours can you see at the market?",
                'image_path' => $this->pageImage($story, $position, $object, 'colouring', '#f59e0b'),
            ]);
        }
        $this->publish($story);
    }

    private function tracingStory(Course $course): void
    {
        $story = $this->story($course, '[Sample Tracing] First Yoruba words', '0_5', 'tracing',
            'Trace simple words, then say them aloud with a parent.', '#34d399');
        $words = [
            ['omi', 'water'], ['ilé', 'home'], ['ọ̀rẹ́', 'friend'], ['ẹ ṣé', 'thank you'], ['bàbá', 'father'],
            ['màmá', 'mother'], ['ọmọ', 'child'], ['ìwé', 'book'], ['oúnjẹ', 'food'], ['ọjọ́', 'day'],
            ['ọ̀nà', 'road'], ['ẹyẹ', 'bird'], ['ìtàn', 'story'],
        ];
        foreach ($words as $index => [$word, $meaning]) {
            $position = $index + 1;
            $story->pages()->firstOrCreate(['position' => $position], [
                'title' => ucfirst($word), 'body' => "Trace “{$word}” and say it aloud. It means {$meaning}.",
                'image_path' => $this->pageImage($story, $position, $word, 'tracing', '#34d399'),
            ]);
        }
        $this->publish($story);
    }

    private function animatedStory(Course $course): void
    {
        $story = $this->story($course, '[Sample Animated] Tortoise and the talking drum', '6_12', 'animated',
            'A short silent sample clip about sharing music. The preview and full video exercise the paid video flow.', '#818cf8');
        foreach (['preview' => 'sample_story_preview.mp4', 'full' => 'sample_story_full.mp4'] as $slot => $file) {
            $path = "stories/{$story->id}/sample_{$slot}.mp4";
            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->put($path, file_get_contents(__DIR__.'/assets/'.$file));
            }
            $field = $slot === 'preview' ? 'preview_video_path' : 'full_video_path';
            if (! $story->{$field}) {
                $story->update([$field => $path]);
            }
        }
        $this->publish($story);
    }

    private function readingStories(Course $course): void
    {
        $stories = [
            ['[Sample Reading] The promise of the talking drum', '13_17', '#f472b6', [
                ['The lost rhythm', 'Tóbi found an old drum beside the village path. Each time he tapped it, the beat echoed a word he had heard from his grandmother: listen. He wanted to show everyone at once, but first he sat with the village drummer and learned how a rhythm can carry a message.'],
                ['A promise kept', 'At the festival, Tóbi played the rhythm carefully. His friends answered with claps, and the elders smiled. He had promised to share the drum rather than keep it for himself. By evening, every child knew the beat, and Tóbi understood why listening had come first.'],
            ]],
            ['[Sample Adult Reading] The council at dusk', '18_plus', '#60a5fa', [
                ['A difficult choice', 'At dusk, the council gathered to decide how to repair the bridge. Two neighbours disagreed about whose work should begin first. Àdérónkẹ́ asked each person to describe what the crossing meant to them. The answers revealed that children, traders, and elders all depended on the same narrow path.'],
                ['The shared path', 'The council chose a plan that divided the labour and kept a safe passage open during the repairs. The neighbours did not agree on every detail, but they worked side by side. Àdérónkẹ́ reminded them that a community is strengthened when its decisions leave room for every voice.'],
            ]],
            ['[Sample Folklore] Why Tortoise carries his shell', 'folklore', '#c084fc', [
                ['A hurried journey', 'Tortoise was invited to a feast across the hill. He packed more food than he needed and tried to hurry ahead of his friends. The basket grew heavy, and before long he had to stop beside a stream. Bird offered to help carry a little, but Tortoise was too proud to share.'],
                ['A home to carry', 'When rain began, Tortoise finally asked his friends for help. Together they made a small shelter and waited for the clouds to pass. Tortoise learned that a lighter load and good company make a journey easier. In this sample retelling, his shell reminds him to carry a home, but to share the road.'],
            ]],
        ];
        foreach ($stories as [$title, $band, $colour, $chapters]) {
            $story = $this->story($course, $title, $band, 'reading',
                'A two-chapter sample for testing the free first chapter, ₦500 unlock, and age access.', $colour);
            foreach ($chapters as $index => [$chapterTitle, $body]) {
                $story->pages()->firstOrCreate(['position' => $index + 1], [
                    'title' => $chapterTitle, 'body' => $body,
                ]);
            }
            $this->publish($story);
        }
    }

    private function story(Course $course, string $title, string $band, string $format, string $summary, string $colour): Story
    {
        $story = $course->stories()->firstOrCreate(['title' => $title], [
            'summary' => $summary, 'age_band' => $band, 'format' => $format,
            'price_minor' => Story::PRICE_MINOR,
        ]);
        $path = "stories/{$story->id}/sample_cover.svg";
        Storage::disk('public')->put($path, $this->svg($title, $format, $colour, false));
        if (! $story->cover_path) {
            $story->update(['cover_path' => $path]);
        }

        return $story;
    }

    private function publish(Story $story): void
    {
        if (! $story->published_at) {
            $story->update(['published_at' => now()]);
        }
    }

    private function pageImage(Story $story, int $position, string $label, string $format, string $colour): string
    {
        $path = "stories/{$story->id}/sample_page_{$position}.svg";
        Storage::disk('local')->put($path, $this->svg($label, $format, $colour, true));

        return $path;
    }

    private function svg(string $label, string $format, string $colour, bool $page): string
    {
        $safe = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $subtitle = $page ? ($format === 'tracing' ? 'TRACE AND SAY' : 'COLOUR THIS PAGE') : 'SAMPLE STORY';
        $title = $page ? $safe : htmlspecialchars((string) preg_replace('/^\[Sample(?: [^\]]+)?\]\s*/', '', $label), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $shape = $format === 'tracing'
            ? '<path d="M160 420 H640" stroke="#cbd5e1" stroke-width="3" stroke-dasharray="14 10"/>'
            : '<circle cx="400" cy="390" r="110" fill="none" stroke="#1e293b" stroke-width="10"/><path d="M400 250 V210 M400 570 V530 M260 390 H220 M580 390 H540" stroke="#1e293b" stroke-width="8" stroke-linecap="round"/>';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <rect width="800" height="600" fill="#f8fafc"/>
  <path d="M0 0 H800 V140 H0 Z" fill="{$colour}"/>
  <text x="400" y="75" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="28" fill="#0f172a">{$subtitle}</text>
  <text x="400" y="190" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="30" fill="#0f172a">{$title}</text>
  {$shape}
  <text x="400" y="565" text-anchor="middle" font-family="Arial,sans-serif" font-size="18" fill="#475569">Mahadum360 sample content</text>
</svg>
SVG;
    }
}
