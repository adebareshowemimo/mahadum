<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
