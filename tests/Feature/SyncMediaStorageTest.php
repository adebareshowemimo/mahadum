<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_files_without_creating_assets(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/Yoruba practice videos/greeting.mp4', 'video');

        $this->assertSame(0, Artisan::call('media:sync-storage', ['--dry-run' => true]));
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertStringContainsString('1 created', Artisan::output());
    }

    public function test_it_imports_identically_named_videos_as_distinct_assets_per_folder(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/Yoruba practice videos/level 1 lesson 1 practice 1.mp4', 'yoruba');
        Storage::disk('public')->put('media/Igbo Practice videos/level 1 lesson 1 practice 1.mp4', 'igbo');
        Storage::disk('public')->put('media/Hausa practice videos/level 1 lesson 1 practice 1.mp4', 'hausa');

        $this->assertSame(0, Artisan::call('media:sync-storage'));

        $this->assertDatabaseCount('media_assets', 3);
        foreach (['Yoruba practice videos', 'Igbo Practice videos', 'Hausa practice videos'] as $folder) {
            $this->assertDatabaseHas('media_assets', [
                'type' => 'video',
                'url' => "media/{$folder}/level 1 lesson 1 practice 1.mp4",
                'original_name' => 'level 1 lesson 1 practice 1.mp4',
                'folder' => $folder,
                'title' => 'level 1 lesson 1 practice 1',
            ]);
        }
    }

    public function test_it_is_idempotent_and_backfills_folder_metadata_from_the_full_path(): void
    {
        Storage::fake('public');
        $path = 'media/Yoruba practice videos/greeting.mp4';
        Storage::disk('public')->put($path, 'video');
        $asset = MediaAsset::create(['type' => 'video', 'url' => $path]);

        Artisan::call('media:sync-storage');
        Artisan::call('media:sync-storage');

        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseHas('media_assets', [
            'id' => $asset->id,
            'url' => $path,
            'folder' => 'Yoruba practice videos',
            'original_name' => 'greeting.mp4',
            'title' => 'greeting',
        ]);
    }
}
