<?php

namespace Tests\Feature;

use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pricing_lists_consumer_plans_and_school_bands(): void
    {
        $this->seed(PlanSeeder::class);

        $data = $this->getJson('/api/v1/pricing')
            ->assertOk()
            ->assertJsonPath('data.free.name', 'Free')
            ->assertJsonPath('data.school.term_months', 9)
            ->json('data');

        // Consumer tiers include the locked monthly + annual prices.
        $codes = collect($data['consumer'])->keyBy('code');
        $this->assertSame(300000, $codes['premium_individual']['price_minor']);
        $this->assertSame(3000000, $codes['premium_individual_annual']['price_minor']);
        $this->assertSame(600000, $codes['premium_family']['price_minor']);
        $this->assertSame(6000000, $codes['premium_family_annual']['price_minor']);

        // School bands carry the registration fee + per-student rate.
        $this->assertSame(5_000_000, $data['school']['bands'][0]['registration_minor']);
        $this->assertSame(700_000, $data['school']['bands'][0]['per_student_minor']);
        $this->assertCount(4, $data['school']['bands']);
    }

    public function test_pricing_is_public(): void
    {
        $this->seed(PlanSeeder::class);
        // No auth header — still 200.
        $this->getJson('/api/v1/pricing')->assertOk();
    }
}
