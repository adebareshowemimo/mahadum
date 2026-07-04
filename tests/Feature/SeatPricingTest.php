<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeatPricingTest extends TestCase
{
    use RefreshDatabase;

    private function schoolAdmin(): array
    {
        $this->seedRbac();
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        return [$org, $admin];
    }

    /**
     * Each band: registration fee + quantity × per-student rate (all in kobo).
     *
     * @return array<string, array{0: int, 1: string, 2: int, 3: int}>
     */
    public static function bands(): array
    {
        return [
            // qty, band label, registration_minor, total amount_minor
            '1–99' => [50, '1–99 students', 5_000_000, 5_000_000 + 50 * 700_000],
            '100–249' => [150, '100–249 students', 10_000_000, 10_000_000 + 150 * 600_000],
            '250–500' => [300, '250–500 students', 15_000_000, 15_000_000 + 300 * 550_000],
            'above 500' => [600, 'Above 500 students', 20_000_000, 20_000_000 + 600 * 500_000],
        ];
    }

    #[DataProvider('bands')]
    public function test_seat_purchase_prices_each_band(int $qty, string $label, int $registration, int $total): void
    {
        [$org] = $this->schoolAdmin();

        $this->postJson("/api/v1/schools/{$org->id}/seats/purchase", ['quantity' => $qty])
            ->assertCreated()
            ->assertJsonPath('data.band', $label)
            ->assertJsonPath('data.registration_minor', $registration)
            ->assertJsonPath('data.amount_minor', $total);
    }

    public function test_band_boundaries_are_inclusive(): void
    {
        [$org] = $this->schoolAdmin();

        // 99 → 1–99 band, 100 → next band, 500 → 250–500, 501 → above 500.
        $this->postJson("/api/v1/schools/{$org->id}/seats/purchase", ['quantity' => 99])
            ->assertJsonPath('data.band', '1–99 students');
        $this->postJson("/api/v1/schools/{$org->id}/seats/purchase", ['quantity' => 100])
            ->assertJsonPath('data.band', '100–249 students');
        $this->postJson("/api/v1/schools/{$org->id}/seats/purchase", ['quantity' => 500])
            ->assertJsonPath('data.band', '250–500 students');
        $this->postJson("/api/v1/schools/{$org->id}/seats/purchase", ['quantity' => 501])
            ->assertJsonPath('data.band', 'Above 500 students');
    }

    public function test_registration_fee_can_be_skipped_for_top_ups(): void
    {
        [$org] = $this->schoolAdmin();

        // A same-year top-up: seats only, no registration fee.
        $this->postJson("/api/v1/schools/{$org->id}/seats/purchase", ['quantity' => 50, 'include_registration' => false])
            ->assertCreated()
            ->assertJsonPath('data.registration_minor', 0)
            ->assertJsonPath('data.amount_minor', 50 * 700_000);
    }
}
