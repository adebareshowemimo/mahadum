<?php

namespace Database\Seeders;

use App\Models\GuideFaq;
use App\Models\GuideVideo;
use Illuminate\Database\Seeder;

/**
 * Review content for the public Guides page. Safe to run repeatedly: existing
 * admin edits are retained and only missing entries are added.
 */
class GuideLibrarySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Example guide FAQs must not be seeded in production.');
        }

        $playlists = [
            'hausa' => [
                ['NAC7DVDMGzM', 'Body Parts in Hausa | Describe Yourself in Hausa for Beginners & Kids'],
                ['mQpaMkPx99M', 'How to Introduce Yourself in Hausa | Easy Hausa Lesson for Beginners & Kids'],
                ['_bfqsbT94dE', 'How to Introduce Yourself in Hausa: Your Job & Where You Are From | Practice 2'],
                ['PBHMJaei_U4', 'Body Parts in Hausa: Eyes, Eye Colour & Complexion | Describe Yourself Practice 1'],
                ['0qe4rpzyir0', 'Body Parts in Hausa: Lips, Hair, Arms & Fingers | Describe Yourself Practice 2'],
                ['jv632KnJJas', 'Talk About Your Family in Hausa: Brothers, Sisters & Pets | Introduce Yourself Practice 3'],
                ['VrJYSCJ7vF4', 'Body Parts in Hausa: Hands, Legs & Feet | Describe Yourself Practice 3'],
                ['MiXJ10lSKbs', 'How to Introduce Yourself in Hausa: Name, Age & Address | Practice 1'],
            ],
            'yoruba' => [
                ['h1okV1fwWOY', 'How to Introduce Yourself in Yoruba | Easy Yoruba Lesson for Beginners & Kids'],
                ['EbKk32s92lM', 'How to Introduce Yourself in Yoruba: Name & Age | Practice 1'],
                ['kb7h79btALY', 'How to Introduce Yourself in Yoruba: Where You Live & Address | Practice 2'],
                ['UZAV_btkr4k', 'How to Introduce Yourself in Yoruba: Your Job & Where You Are From | Practice 3'],
                ['OUTfRq5Bpxw', 'Body Parts in Yoruba | Describe Yourself in Yoruba for Beginners & Kids'],
                ['1B1j3E6raeE', 'Body Parts in Yoruba: Hair, Hands & Palms | Describe Yourself Practice 2'],
                ['ecegpvZWQR8', 'Body Parts in Yoruba: Eyes & Eye Colour | Describe Yourself Practice 1'],
                ['0YItuxjBmYI', 'Body Parts in Yoruba: Stomach, Feet & Toes | Describe Yourself Practice 3'],
            ],
            'igbo' => [
                ['c0B5ad3ONZQ', 'How to Introduce Yourself in Igbo Language | Easy Igbo Lesson for Beginners & Kids'],
                ['JI-FVqFIJnI', 'How to Introduce Yourself in Igbo: Name & Age | Practice 1'],
                ['dXlnYuLRHBE', 'How to Introduce Yourself in Igbo: Where You Live & Address | Practice 2'],
                ['obd2nRaUHts', 'How to Introduce Yourself in Igbo: Your Job & Where You Are From | Practice 3'],
                ['u-9uUFn2bd4', 'Body Parts in Igbo | Describe Yourself in Igbo for Beginners & Kids'],
                ['QuWkNI-bAxs', 'Body Parts in Igbo: Eyes & Eye Colour | Describe Yourself Practice 1'],
                ['ijLq0LpRMqY', 'Body Parts in Igbo: Nose, Ears, Lips & Hair | Describe Yourself Practice 2'],
                ['qBNoj4s3UZ4', 'Body Parts in Igbo: Hands, Stomach, Legs & Feet | Describe Yourself Practice 3'],
            ],
        ];

        $languagePosition = ['hausa' => 0, 'yoruba' => 1, 'igbo' => 2];
        foreach ($playlists as $language => $videos) {
            foreach ($videos as $index => [$youtubeId, $title]) {
                $position = ($index * 3) + $languagePosition[$language] + 1;
                foreach (['sample', 'how_to'] as $section) {
                    GuideVideo::firstOrCreate(
                        ['section' => $section, 'youtube_id' => $youtubeId],
                        [
                            'title' => $title,
                            'description' => $section === 'sample'
                                ? 'A short '.$language.' lesson from the Mahadum360 YouTube playlist.'
                                : 'Follow along with this '.$language.' language lesson.',
                            'language' => $language,
                            'topic' => $section === 'how_to' ? 'learning' : null,
                            'source_type' => 'youtube',
                            'sort_order' => $position,
                            'is_visible' => true,
                        ],
                    );
                }
            }
        }

        $faqs = [
            ['How do I start learning?', 'Example answer: Create an account, choose a learner profile, and select a course to begin. Replace this example with approved support copy.'],
            ['Which languages can I explore?', 'Example answer: Browse the available Yorùbá, Igbo, Hausa and English courses. Replace this example with approved support copy.'],
            ['Can I switch between learner profiles?', 'Example answer: Parents can choose a learner profile from the profile switcher. Replace this example with approved support copy.'],
            ['Where do I find stories and proverbs?', 'Example answer: Open the Stories or Proverbs & Trivia section from the learning menu. Replace this example with approved support copy.'],
            ['What should I do if a video does not play?', 'Example answer: Check your connection and try again. If the issue continues, contact support. Replace this example with approved support copy.'],
        ];
        foreach ($faqs as $index => [$question, $answer]) {
            GuideFaq::firstOrCreate(['question' => $question], ['answer' => $answer, 'sort_order' => $index + 1, 'is_visible' => true]);
        }
    }
}
