<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Billing tiers. Prices are integer MINOR units (kobo) in NGN, matching the
 * `plans` table convention (price_minor + currency). Adjust the numbers once
 * commercial pricing is locked — codes/structure are what the app keys on.
 *
 * max_profiles: how many learner_profiles the tier supports (null = unlimited,
 * resolved per seat for school plans).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free', 'name' => 'Free', 'price_minor' => 0,
                'currency' => 'NGN', 'interval' => 'month', 'max_profiles' => 1,
                'features' => ['ads' => true, 'offline_download' => false, 'unlimited_hearts' => false],
            ],
            [
                'code' => 'premium_individual', 'name' => 'Premium (Individual)', 'price_minor' => 150000,
                'currency' => 'NGN', 'interval' => 'month', 'max_profiles' => 1,
                'features' => ['ads' => false, 'offline_download' => true, 'unlimited_hearts' => true],
            ],
            [
                'code' => 'premium_family', 'name' => 'Premium (Family)', 'price_minor' => 350000,
                'currency' => 'NGN', 'interval' => 'month', 'max_profiles' => 6,
                'features' => ['ads' => false, 'offline_download' => true, 'unlimited_hearts' => true, 'family_dashboard' => true],
            ],
            [
                'code' => 'school_term', 'name' => 'School (Per Term)', 'price_minor' => 0,
                'currency' => 'NGN', 'interval' => 'term', 'max_profiles' => null,
                'features' => ['ads' => false, 'seats' => true, 'teacher_analytics' => true, 'priced_per_seat' => true],
            ],
            [
                'code' => 'school_annual', 'name' => 'School (Annual)', 'price_minor' => 0,
                'currency' => 'NGN', 'interval' => 'year', 'max_profiles' => null,
                'features' => ['ads' => false, 'seats' => true, 'teacher_analytics' => true, 'priced_per_seat' => true],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
