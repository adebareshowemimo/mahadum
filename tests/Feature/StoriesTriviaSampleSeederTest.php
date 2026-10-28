<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Story;
use Database\Seeders\StoriesTriviaSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoriesTriviaSampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_samples_are_complete_and_a_second_run_does_not_duplicate_them(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->seedRbac();
        $this->userWithRole('content_owner');

        $this->seed(StoriesTriviaSampleSeeder::class);
        $this->seed(StoriesTriviaSampleSeeder::class);

        $course = Course::where('title', '[Sample] Yoruba Stories & Wisdom')->firstOrFail();
        $this->assertSame(2, $course->levels()->firstOrFail()->lessons()->count());
        $this->assertSame(6, $course->stories()->count());
        $this->assertSame(1, $course->stories()->where('format', 'animated')->count());

        $animated = Story::where('course_id', $course->id)->where('format', 'animated')->firstOrFail();
        $this->assertTrue(Storage::disk('public')->exists($animated->cover_path));
        $this->assertTrue(Storage::disk('local')->exists($animated->preview_video_path));
        $this->assertTrue(Storage::disk('local')->exists($animated->full_video_path));
    }

    public function test_production_requires_an_explicit_sample_content_flag(): void
    {
        $previousEnvironment = app()->environment();
        $previousFlag = getenv('SEED_STORIES_TRIVIA_SAMPLES');

        try {
            putenv('SEED_STORIES_TRIVIA_SAMPLES');
            app()->detectEnvironment(fn () => 'production');
            (new StoriesTriviaSampleSeeder)->run();
            $this->fail('Production sample content was seeded without an explicit flag.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('SEED_STORIES_TRIVIA_SAMPLES=1', $exception->getMessage());
        } finally {
            app()->detectEnvironment(fn () => $previousEnvironment);
            putenv($previousFlag === false ? 'SEED_STORIES_TRIVIA_SAMPLES' : "SEED_STORIES_TRIVIA_SAMPLES={$previousFlag}");
        }
    }
}
