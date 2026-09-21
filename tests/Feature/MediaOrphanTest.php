<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaOrphanTest extends TestCase
{
    use RefreshDatabase;

    private function asset(string $name): MediaAsset
    {
        return MediaAsset::create(['type' => 'image', 'url' => "media/{$name}.png", 'original_name' => "{$name}.png"]);
    }

    public function test_orphans_lists_only_unreferenced_assets(): void
    {
        $this->seedRbac();
        $used = $this->asset('used');
        $orphan = $this->asset('orphan');
        // Reference `used` from a video so it is no longer an orphan.
        DB::table('videos')->insert(['title' => 'Lesson clip', 'source_asset_id' => $used->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->getJson('/api/v1/media/orphans')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $orphan->id);
    }

    public function test_purge_deletes_orphans_and_skips_referenced(): void
    {
        $this->seedRbac();
        $used = $this->asset('used');
        $orphan = $this->asset('orphan');
        DB::table('videos')->insert(['title' => 'Lesson clip', 'source_asset_id' => $used->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->postJson('/api/v1/media/orphans/purge', ['ids' => [$used->id, $orphan->id]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertDatabaseMissing('media_assets', ['id' => $orphan->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $used->id]); // referenced → kept
    }

    public function test_orphans_requires_media_permission(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('student'));

        $this->getJson('/api/v1/media/orphans')->assertStatus(403);
    }

    public function test_uploaded_video_returns_a_same_origin_playable_url(): void
    {
        $this->seedRbac();
        Storage::fake('public');
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->postJson('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('lesson.mp4', 128, 'video/mp4'),
        ])->assertCreated()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.url', fn ($url) => str_starts_with($url, '/storage/media/'));
    }

    public function test_directory_upload_preserves_folder_and_library_reports_video_counts(): void
    {
        $this->seedRbac();
        Storage::fake('public');
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->postJson('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('greeting.mp4', 128, 'video/mp4'),
            'folder' => 'Yoruba/Week 1',
        ])->assertCreated()
            ->assertJsonPath('data.folder', 'Yoruba/Week 1');

        MediaAsset::create([
            'type' => 'image',
            'url' => 'media/poster.png',
            'original_name' => 'poster.png',
            'folder' => 'Yoruba/Week 1',
        ]);
        MediaAsset::create([
            'type' => 'video',
            'url' => 'media/unfiled.mp4',
            'original_name' => 'unfiled.mp4',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/media?folder=Yoruba%2FWeek%201')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.type_counts.video', 1)
            ->assertJsonPath('meta.type_counts.image', 1)
            ->assertJsonPath('folders.1.name', 'Yoruba/Week 1')
            ->assertJsonPath('folders.1.total', 2)
            ->assertJsonPath('folders.1.video_count', 1);

        $aggregateQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query) => str_contains(strtolower($query), 'group by'));

        $this->assertNotEmpty($aggregateQueries);
        $aggregateQueries->each(fn (string $query) => $this->assertStringNotContainsString('created_at', strtolower($query)));
        DB::disableQueryLog();

        $this->getJson('/api/v1/media?folder=__unfiled')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.original_name', 'unfiled.mp4');
    }

    public function test_mp4_with_generic_binary_mime_is_accepted_and_keeps_a_video_extension(): void
    {
        $this->seedRbac();
        Storage::fake('public');
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->postJson('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('camera-export.mp4', 128, 'application/octet-stream'),
        ])->assertCreated()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.url', fn ($url) => str_ends_with($url, '.mp4'));
    }

    public function test_media_upload_accepts_up_to_300_mb_and_rejects_larger_files(): void
    {
        $this->seedRbac();
        Storage::fake('public');
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->postJson('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('maximum.mp4', 307200, 'video/mp4'),
        ])->assertCreated();

        $this->postJson('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('too-large.mp4', 307201, 'video/mp4'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }
}
