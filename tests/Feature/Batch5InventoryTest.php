<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\XpLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class Batch5InventoryTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_inventory_preserves_exact_records_and_same_name_learner_identity_without_http_or_secrets(): void
    {
        Http::preventStrayRequests();
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $first = $this->parentWithChild($parent);
        $first->update(['display_name' => 'Lucy Okafor']);
        $second = $first->family->learnerProfiles()->create(['display_name' => 'Lucy Okafor']);
        XpLedger::create(['learner_profile_id' => $first->id, 'amount' => 16, 'source' => 'test']);
        XpLedger::create(['learner_profile_id' => $second->id, 'amount' => 7, 'source' => 'test']);
        $school = Organization::create(['name' => 'Reilly-Hickle Academy', 'slug' => 'candidate', 'type' => 'school', 'status' => 'active']);
        DB::table('organization_user')->insert(['organization_id' => $school->id, 'user_id' => $parent->id, 'role' => 'parent', 'status' => 'active']);
        Organization::create(['name' => 'Real School', 'slug' => 'real', 'type' => 'school', 'status' => 'active']);
        config(['services.monnify.api_key' => 'private-key-never-output', 'services.monnify.secret' => 'private-secret-never-output']);
        $before = DB::table('organizations')->orderBy('id')->get()->toJson();
        $this->assertSame(0, Artisan::call('audit:batch-five', ['--user' => (string) $parent->id]));
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($parent->id, $report['account']['user_id']);
        $this->assertCount(2, $report['same_name_learners_not_merge_targets']);
        $this->assertSame(16, (int) $report['same_name_learners_not_merge_targets'][0]['total_xp']);
        $this->assertSame(7, (int) $report['same_name_learners_not_merge_targets'][1]['total_xp']);
        $this->assertCount(1, $report['cleanup_candidates_not_approved_targets']['organizations']);
        $this->assertSame(1, $report['cleanup_candidates_not_approved_targets']['organizations'][0]['dependencies']['organization_user.organization_id']);
        $this->assertStringNotContainsString('private-key-never-output', $output);
        $this->assertStringNotContainsString('private-secret-never-output', $output);
        $this->assertSame($before, DB::table('organizations')->orderBy('id')->get()->toJson());
        $this->assertSame(2, DB::table('learner_profiles')->count());
        Http::assertNothingSent();
    }

    public function test_unknown_exact_account_returns_failure_without_guessing_a_relink(): void
    {
        $this->assertSame(1, Artisan::call('audit:batch-five', ['--user' => 'unknown@example.test']));
        $this->assertStringContainsString('No records changed', Artisan::output());
        $this->assertSame(0, DB::table('families')->count());
    }
}
