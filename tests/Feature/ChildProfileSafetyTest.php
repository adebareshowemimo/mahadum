<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\Competition;
use App\Models\Language;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\SchoolClass;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ChildProfileSafetyTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_child_token_blocks_parent_apis_until_parent_pin_unlocks_it(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $child = $this->parentWithChild($parent);
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $story = Story::create(['language_id' => $language->id, 'standalone_level' => 1, 'title' => 'Child safety story', 'age_band' => '0_5', 'format' => 'reading', 'price_minor' => Story::PRICE_MINOR, 'published_at' => now()]);
        $competition = Competition::create(['title' => 'Child safety competition', 'slug' => 'child-safety-competition', 'season' => 2026, 'status' => 'open']);
        $this->withToken($parent->createToken('child-mode-test')->plainTextToken);

        $this->putJson("/api/v1/family/children/{$child->id}/pin", ['pin' => '1234'])->assertOk();
        $this->postJson("/api/v1/profiles/{$child->id}/switch", ['pin' => '1234'])->assertOk();
        $this->getJson('/api/v1/wallet')->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->getJson('/api/v1/subscriptions')->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->getJson('/api/v1/data-bundles')->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->getJson('/api/v1/payouts')->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->getJson('/api/v1/family')->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->getJson('/api/v1/me/notifications')->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->postJson("/api/v1/learning/stories/{$story->id}/purchase", [])->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [])->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->getJson('/api/v1/public/competitions/'.$competition->slug)->assertOk()->assertJsonPath('data.can_enter', false);
        $refreshed = $this->postJson('/api/v1/auth/refresh')->assertOk()->json('data.token');
        $this->withToken($refreshed);
        $this->getJson('/api/v1/wallet')->assertForbidden()->assertJsonPath('error.code', 'parent_only');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.active_child_profile_id', $child->id);
        $self = LearnerProfile::create(['user_id' => $parent->id, 'display_name' => $parent->name, 'age_band' => 'adult']);
        $this->postJson("/api/v1/profiles/{$self->id}/switch", ['from_learner_id' => $child->id])
            ->assertForbidden()->assertJsonPath('error.code', 'invalid_pin');
        $this->postJson('/api/v1/profiles/exit', ['pin' => '9999'])->assertForbidden();
        $this->getJson('/api/v1/wallet')->assertForbidden();
        $this->postJson('/api/v1/profiles/exit', ['pin' => '1234'])->assertOk();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.active_child_profile_id', null);
        $this->getJson('/api/v1/wallet')->assertOk();
    }

    public function test_child_session_exposes_only_child_identity_and_an_enrolled_school(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $child = $this->parentWithChild($parent);
        $this->withToken($parent->createToken('child-privacy-test')->plainTextToken);
        $this->putJson("/api/v1/family/children/{$child->id}/pin", ['pin' => '1234'])->assertOk();
        $this->postJson("/api/v1/profiles/{$child->id}/switch", ['pin' => '1234'])->assertOk();

        $school = Organization::create(['name' => 'A real school', 'slug' => 'real-school', 'type' => 'school', 'status' => 'active']);
        OrganizationUser::create(['organization_id' => $school->id, 'user_id' => $parent->id, 'role' => 'parent', 'status' => 'active']);

        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.user.name', $child->display_name)
            ->assertJsonPath('data.user.email', '')
            ->assertJsonPath('data.user.roles.0', 'student')
            ->assertJsonPath('data.learner_profiles.0.school_name', null)
            ->assertJsonCount(0, 'data.families')
            ->assertJsonCount(0, 'data.organizations')
            ->assertJsonPath('data.subscription', null);

        $child->update(['organization_id' => $school->id]);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.learner_profiles.0.school_name', null);

        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class One']);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $child->id]);
        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.learner_profiles.0.school_name', 'A real school')
            ->assertJsonCount(0, 'data.organizations');
    }
}
