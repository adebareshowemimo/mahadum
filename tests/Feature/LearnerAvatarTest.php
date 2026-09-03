<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\LearnerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearnerAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_choose_a_builtin_avatar_for_their_child(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $child = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Ada']);

        $this->postJson("/api/v1/learners/{$child->id}/avatar", ['avatar_id' => 4])
            ->assertOk()
            ->assertJsonPath('data.avatar_id', 4)
            ->assertJsonPath('data.avatar_url', null);

        $this->assertDatabaseHas('learner_profiles', ['id' => $child->id, 'avatar_id' => 4]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'learner.avatar_updated', 'subject_id' => $child->id]);
    }

    public function test_parent_can_upload_and_replace_a_child_photo(): void
    {
        Storage::fake('public');
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $child = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Tunde', 'avatar_id' => 2]);

        $response = $this->post("/api/v1/learners/{$child->id}/avatar", [
            'photo' => UploadedFile::fake()->image('profile.jpg', 300, 300),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNull($response->json('data.avatar_id'));
        $this->assertNotNull($response->json('data.avatar_url'));
        $assetId = $child->fresh()->profile_photo_asset_id;
        $this->assertDatabaseHas('learner_profiles', ['id' => $child->id, 'profile_photo_asset_id' => $assetId]);
        $this->assertDatabaseHas('media_assets', ['id' => $assetId, 'type' => 'image', 'uploaded_by' => $parent->id]);
    }

    public function test_learner_can_change_their_own_avatar_but_not_another_profile(): void
    {
        $this->seedRbac();
        $student = $this->actingAsUser($this->userWithRole('student'));
        $own = LearnerProfile::create(['user_id' => $student->id, 'display_name' => 'Self']);
        $other = LearnerProfile::create(['display_name' => 'Other']);

        $this->postJson("/api/v1/learners/{$own->id}/avatar", ['avatar_id' => 8])
            ->assertOk()
            ->assertJsonPath('data.avatar_id', 8);
        $this->postJson("/api/v1/learners/{$other->id}/avatar", ['avatar_id' => 7])->assertForbidden();
    }

    public function test_avatar_upload_validation_rejects_invalid_files_and_unknown_presets(): void
    {
        Storage::fake('public');
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $child = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Kemi']);

        $this->postJson("/api/v1/learners/{$child->id}/avatar", ['avatar_id' => 99])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar_id');
        $this->post("/api/v1/learners/{$child->id}/avatar", [
            'photo' => UploadedFile::fake()->create('profile.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }
}
