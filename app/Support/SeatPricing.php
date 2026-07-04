<?php

namespace App\Support;

/**
 * School seat pricing (launch financials) — the single source of truth shared by
 * the seat-purchase flow (SeatController) and the public pricing page
 * (PricingController). An annual registration fee plus an absolute per-student
 * price, both stepping down by student-count band. All amounts are MINOR units
 * (kobo); bands are ordered ascending with a null-max open-ended top tier.
 */
final class SeatPricing
{
    /**
     * @var list<array{max: int|null, registration_minor: int, per_student_minor: int, label: string}>
     */
    public const BANDS = [
        ['max' => 99, 'registration_minor' => 5_000_000, 'per_student_minor' => 700_000, 'label' => '1–99 students'],
        ['max' => 249, 'registration_minor' => 10_000_000, 'per_student_minor' => 600_000, 'label' => '100–249 students'],
        ['max' => 500, 'registration_minor' => 15_000_000, 'per_student_minor' => 550_000, 'label' => '250–500 students'],
        ['max' => null, 'registration_minor' => 20_000_000, 'per_student_minor' => 500_000, 'label' => 'Above 500 students'],
    ];

    /** Academic year the annual registration covers. */
    public const TERM_MONTHS = 9;

    /**
     * First band whose `max` the quantity fits within (the final null-max band is
     * the open-ended top tier).
     *
     * @return array{max: int|null, registration_minor: int, per_student_minor: int, label: string}
     */
    public static function bandFor(int $qty): array
    {
        foreach (self::BANDS as $band) {
            if ($band['max'] === null || $qty <= $band['max']) {
                return $band;
            }
        }

        return self::BANDS[array_key_last(self::BANDS)];
    }
}
