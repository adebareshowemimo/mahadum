<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionTest extends TestCase
{
    use RefreshDatabase;

    private function openCompetition(array $attrs = []): Competition
    {
        return Competition::create(array_merge([
            'title' => 'Language & Culture 2026',
            'slug' => 'language-culture-2026',
            'season' => 2026,
            'status' => 'open',
            'min_activity_days' => 90,
        ], $attrs));
    }

    public function test_content_owner_creates_and_opens_a_competition(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));

        $created = $this->postJson('/api/v1/admin/competitions', [
            'title' => 'Language & Culture 2026',
            'season' => 2026,
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/admin/competitions/{$created['id']}/status", ['status' => 'open'])
            ->assertOk()->assertJsonPath('data.status', 'open');

        // A draft competition is hidden from the public browse.
        $this->getJson('/api/v1/competitions')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_school_admin_enters_a_play_and_users_vote_once_per_category(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();

        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);

        $this->actingAsUser($admin);
        $entry = $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'school_play',
            'title' => 'The Talking Drum',
            'organization_id' => $org->id,
        ])->assertCreated()->json('data');

        // Any signed-in user can vote — but only once in the school_play category.
        $voter = $this->userWithRole('student');
        $this->actingAsUser($voter);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry['id']}/vote")
            ->assertCreated()->assertJsonPath('data.votes_count', 1);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry['id']}/vote")
            ->assertStatus(409);
    }

    public function test_parent_enters_child_only_when_activity_threshold_is_met(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();

        $parent = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Ade Family']);

        // A learner with no activity does not qualify — even if the account is old.
        $fresh = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Tunde']);
        $fresh->forceFill(['created_at' => now()->subDays(200)])->save();
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'diaspora_folklore',
            'title' => 'The Tortoise and the Birds',
            'learner_profile_id' => $fresh->id,
        ])->assertStatus(422);

        // A learner whose first XP was earned beyond the threshold qualifies.
        $eligible = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Bisi']);
        $eligible->xpEntries()->create(['amount' => 20, 'source' => 'lesson', 'created_at' => now()->subDays(120)]);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'diaspora_folklore',
            'title' => 'The Tortoise and the Birds',
            'learner_profile_id' => $eligible->id,
        ])->assertCreated();
    }

    public function test_parent_cannot_enter_a_child_they_do_not_own(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();

        $owner = $this->userWithRole('parent');
        $otherFamily = Family::create(['owner_user_id' => $owner->id, 'name' => 'Owner Family']);
        $child = LearnerProfile::create(['family_id' => $otherFamily->id, 'display_name' => 'Not Yours']);
        $child->forceFill(['created_at' => now()->subDays(200)])->save();

        $intruder = $this->userWithRole('parent');
        $this->actingAsUser($intruder);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'diaspora_folklore',
            'title' => 'Stolen Story',
            'learner_profile_id' => $child->id,
        ])->assertStatus(403);
    }

    public function test_organiser_lists_and_moderates_entries(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $entry = $competition->entries()->create([
            'category' => 'school_play', 'organization_id' => $org->id, 'title' => 'A Play', 'status' => 'submitted',
        ]);

        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->getJson("/api/v1/admin/competitions/{$competition->id}")
            ->assertOk()
            ->assertJsonPath('data.entries.0.title', 'A Play')
            ->assertJsonPath('data.entries.0.entrant', 'Greenfield');

        $this->postJson("/api/v1/admin/competitions/{$competition->id}/entries/{$entry->id}/moderate", ['status' => 'disqualified'])
            ->assertOk()->assertJsonPath('data.status', 'disqualified');

        $this->assertSame('disqualified', $entry->refresh()->status);
    }

    public function test_organiser_judges_and_closes_the_competition(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $entry = $competition->entries()->create([
            'category' => 'school_play', 'organization_id' => $org->id, 'title' => 'A Play', 'status' => 'approved',
        ]);

        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/admin/competitions/{$competition->id}/judge", [
            'awards' => [['entry_id' => $entry->id, 'rank' => 1]],
        ])->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertSame(1, $entry->refresh()->award_rank);
    }

    public function test_entries_are_rejected_once_submissions_close(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition(['status' => 'voting']); // past entry window
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);

        $this->actingAsUser($admin);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'school_play',
            'title' => 'Too Late',
            'organization_id' => $org->id,
        ])->assertStatus(422);
    }
}
