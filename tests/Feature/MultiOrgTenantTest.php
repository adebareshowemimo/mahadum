<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A staff member belonging to 2+ schools used to get NO active tenant at all
 * unless they explicitly sent X-Organization-Id — every request without that
 * header (e.g. the org switcher's own default rendering, or any request made
 * before the user manually touches the switcher) resolved to a null tenant:
 * tenant-scoped reads ran fully unscoped, and tenant-scoped writes 500'd on
 * the NOT NULL organization_id constraint. IdentifyTenant now defaults to the
 * first active membership instead of leaving multi-org users in that limbo.
 */
class MultiOrgTenantTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $slug): Organization
    {
        return Organization::create(['name' => ucfirst($slug), 'type' => 'school', 'slug' => $slug, 'status' => 'active']);
    }

    /** A teacher belonging to both $first and $second, in that membership order. */
    private function teacherOfBoth(Organization $first, Organization $second): User
    {
        $teacher = $this->userWithRole('teacher');
        $first->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $second->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);

        return $teacher;
    }

    public function test_a_multi_org_user_defaults_to_their_first_membership_without_a_header(): void
    {
        $this->seedRbac();
        $first = $this->org('first-school');
        $second = $this->org('second-school');
        $teacher = $this->teacherOfBoth($first, $second);

        SchoolClass::withoutGlobalScopes()->create(['organization_id' => $first->id, 'name' => 'First class', 'level' => 'JSS1']);
        SchoolClass::withoutGlobalScopes()->create(['organization_id' => $second->id, 'name' => 'Second class', 'level' => 'JSS1']);

        $this->actingAsUser($teacher);

        // No X-Organization-Id header sent — must scope to the first membership
        // (by organization id), not run unscoped across both schools.
        $names = collect($this->getJson('/api/v1/classes')->assertOk()->json('data'))->pluck('name');
        $this->assertContains('First class', $names);
        $this->assertNotContains('Second class', $names);
    }

    public function test_the_header_still_overrides_the_default_for_a_multi_org_user(): void
    {
        $this->seedRbac();
        $first = $this->org('first-school');
        $second = $this->org('second-school');
        $teacher = $this->teacherOfBoth($first, $second);

        SchoolClass::withoutGlobalScopes()->create(['organization_id' => $first->id, 'name' => 'First class', 'level' => 'JSS1']);
        SchoolClass::withoutGlobalScopes()->create(['organization_id' => $second->id, 'name' => 'Second class', 'level' => 'JSS1']);

        $this->actingAsUser($teacher);

        $names = collect(
            $this->getJson('/api/v1/classes', ['X-Organization-Id' => (string) $second->id])->assertOk()->json('data')
        )->pluck('name');
        $this->assertContains('Second class', $names);
        $this->assertNotContains('First class', $names);
    }

    public function test_a_multi_org_teacher_can_create_an_assignment_without_sending_the_header(): void
    {
        $this->seedRbac();
        $first = $this->org('first-school');
        $second = $this->org('second-school');
        $teacher = $this->teacherOfBoth($first, $second);

        $class = SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $first->id, 'name' => 'First class', 'level' => 'JSS1', 'teacher_user_id' => $teacher->id,
        ]);

        $this->actingAsUser($teacher);

        // This is the exact repro from the live review: no X-Organization-Id header,
        // the previous behaviour left organization_id unset and the insert 500'd.
        $this->postJson("/api/v1/classes/{$class->id}/assignments", ['title' => 'Spellings'])
            ->assertCreated();

        $this->assertDatabaseHas('class_assignments', [
            'school_class_id' => $class->id,
            'organization_id' => $first->id,
        ]);
    }
}
