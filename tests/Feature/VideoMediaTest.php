<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Services\Content\LessonPublishService;
use App\Services\Content\StoryPreviewInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class VideoMediaTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_author_can_set_and_edit_cultural_video_rules(): void
    {
        $this->seedRbac();
        $lesson = $this->publishedLesson();
        $component = $lesson->components->firstWhere('type', 'video');
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->putJson("/api/v1/components/{$component->id}", [
            'type' => 'video', 'settings' => ['require_watch' => true],
            'video' => ['title' => 'Festival', 'is_cultural' => true, 'duration_seconds' => 120],
        ])->assertOk()->assertJsonPath('data.detail.is_cultural', true)
            ->assertJsonPath('data.detail.duration_seconds', 120);

        $this->assertDatabaseHas('videos', [
            'lesson_component_id' => $component->id, 'is_cultural' => 1, 'duration_seconds' => 120,
        ]);
    }

    public function test_cultural_video_publish_requires_length_dual_captions_qualities_and_full_watch(): void
    {
        $lesson = $this->publishedLesson();
        $component = $lesson->components->firstWhere('type', 'video');
        $video = $component->video;
        $video->update(['is_cultural' => true, 'duration_seconds' => 120, 'source_type' => 'upload']);
        $failures = app(LessonPublishService::class)->failures($lesson->fresh());
        $this->assertCount(3, $failures);

        $component->update(['settings' => ['require_watch' => true]]);
        foreach (['en', 'yo'] as $language) {
            $video->captions()->create(['language_code' => $language, 'format' => 'vtt', 'url' => "/{$language}.vtt"]);
        }
        foreach (['240p', '360p', '720p'] as $quality) {
            $video->renditions()->create(['quality' => $quality, 'protocol' => 'mp4',
                'manifest_url' => "/{$quality}.mp4", 'ready' => true]);
        }
        $this->assertSame([], app(LessonPublishService::class)->failures($lesson->fresh()));
    }

    public function test_caption_upload_is_visible_to_the_learner_and_rejects_non_vtt(): void
    {
        $this->seedRbac();
        Storage::fake('public');
        $lesson = $this->publishedLesson();
        $video = $lesson->components()->where('type', 'video')->firstOrFail()->video;
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->postJson("/api/v1/content/videos/{$video->id}/captions", [
            'language_code' => 'en',
            'file' => UploadedFile::fake()->createWithContent('captions.vtt', "WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHello\n"),
        ])->assertCreated()->assertJsonPath('data.language_code', 'en');
        $this->assertDatabaseHas('captions', ['video_id' => $video->id, 'language_code' => 'en', 'format' => 'vtt']);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play")
            ->assertOk()->assertJsonPath('data.components.0.video.captions.0.language', 'en');
        $this->postJson("/api/v1/content/videos/{$video->id}/captions", [
            'language_code' => 'en', 'file' => UploadedFile::fake()->createWithContent('wrong.vtt', 'not captions'),
        ])->assertStatus(422);
        $this->assertSame(1, $video->captions()->count());
    }

    public function test_text_free_video_can_be_transcoded_into_three_playable_qualities(): void
    {
        $ffmpeg = new Process([(string) config('services.video.ffmpeg', 'ffmpeg'), '-version']);
        try {
            $ffmpeg->run();
        } catch (\Throwable) {
            $this->markTestSkipped('FFmpeg is unavailable in this environment.');
        }
        if (! $ffmpeg->isSuccessful()) {
            $this->markTestSkipped('FFmpeg is unavailable in this environment.');
        }

        $this->seedRbac();
        Storage::fake('public');
        $lesson = $this->publishedLesson();
        $video = $lesson->components()->where('type', 'video')->firstOrFail()->video;
        $source = Storage::disk('public')->path('media/text-free.mp4');
        Storage::disk('public')->makeDirectory('media');
        (new Process([(string) config('services.video.ffmpeg', 'ffmpeg'), '-y', '-f', 'lavfi',
            '-i', 'color=c=black:s=1280x720:d=1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $source]))->mustRun();
        $this->assertEqualsWithDelta(1.0, app(StoryPreviewInspector::class)->durationSeconds($source), 0.1);
        $asset = MediaAsset::create(['type' => 'video', 'url' => 'media/text-free.mp4']);
        $video->update(['source_asset_id' => $asset->id, 'source_type' => 'upload']);
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->postJson("/api/v1/content/videos/{$video->id}/transcode")
            ->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonCount(3, 'data.renditions');
        $this->assertSame(1, $video->fresh()->duration_seconds);
        foreach ([240, 360, 720] as $height) {
            $this->assertTrue(Storage::disk('public')->exists("media/renditions/{$video->id}/{$height}p.mp4"));
        }
    }
}
