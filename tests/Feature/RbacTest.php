<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permission_matrix(): void
    {
        $this->seedRbac();

        $super = $this->userWithRole('super_admin');
        $teacher = $this->userWithRole('teacher');
        $parent = $this->userWithRole('parent');
        $content = $this->userWithRole('content_owner');

        // super_admin passes everything via Gate::before
        $this->assertTrue($super->can('payouts.approve'));
        $this->assertTrue($super->can('anything.not.even.defined'));

        // teacher: classroom yes, settlement no
        $this->assertTrue($teacher->can('schools.assignments.create'));
        $this->assertFalse($teacher->can('payouts.approve'));

        // parent: family yes, content no
        $this->assertTrue($parent->can('family.wallet.fund'));
        $this->assertFalse($parent->can('content.courses.publish'));

        // content_owner: CMS yes, billing no
        $this->assertTrue($content->can('content.courses.publish'));
        $this->assertFalse($content->can('billing.plans.manage'));
    }

    public function test_non_admin_blocked_from_admin_metrics(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->getJson('/api/v1/admin/metrics')->assertStatus(403);
    }
}
