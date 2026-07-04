<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Billing tiers. Prices are integer MINOR units (kobo) in NGN, matching the
 * `plans` table convention (price_minor + currency). Amounts follow the locked
 * launch financials: Individual ₦3,000/mo (₦30,000/yr), Family ₦6,000/mo
 * (₦60,000/yr, up to 6). Daily airtime billing is derived as price_minor/30 by
 * TelcoController, so the monthly figures yield ₦100 / ₦200 a day automatically.
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
                'code' => 'premium_individual', 'name' => 'Premium (Individual)', 'price_minor' => 300000,
                'currency' => 'NGN', 'interval' => 'month', 'audience' => 'individual', 'max_profiles' => 1,
                'features' => ['ads' => false, 'offline_download' => true, 'unlimited_hearts' => true],
            ],
            [
                'code' => 'premium_individual_annual', 'name' => 'Premium (Individual, Annual)', 'price_minor' => 3000000,
                'currency' => 'NGN', 'interval' => 'year', 'audience' => 'individual', 'max_profiles' => 1,
                'features' => ['ads' => false, 'offline_download' => true, 'unlimited_hearts' => true],
            ],
            [
                'code' => 'premium_family', 'name' => 'Premium (Family)', 'price_minor' => 600000,
                'currency' => 'NGN', 'interval' => 'month', 'audience' => 'family', 'max_profiles' => 6,
                'features' => ['ads' => false, 'offline_download' => true, 'unlimited_hearts' => true, 'family_dashboard' => true],
            ],
            [
                'code' => 'premium_family_annual', 'name' => 'Premium (Family, Annual)', 'price_minor' => 6000000,
                'currency' => 'NGN', 'interval' => 'year', 'audience' => 'family', 'max_profiles' => 6,
                'features' => ['ads' => false, 'offline_download' => true, 'unlimited_hearts' => true, 'family_dashboard' => true],
            ],
            [
                'code' => 'school_term', 'name' => 'School (Per Term)', 'price_minor' => 0,
                'currency' => 'NGN', 'interval' => 'term', 'audience' => 'school', 'max_profiles' => null,
                'features' => ['ads' => false, 'seats' => true, 'teacher_analytics' => true, 'priced_per_seat' => true, 'language_club' => true],
            ],
            [
                'code' => 'school_annual', 'name' => 'School (Annual)', 'price_minor' => 0,
                'currency' => 'NGN', 'interval' => 'year', 'audience' => 'school', 'max_profiles' => null,
                'features' => ['ads' => false, 'seats' => true, 'teacher_analytics' => true, 'priced_per_seat' => true, 'language_club' => true],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
