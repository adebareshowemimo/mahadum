<?php

namespace App\Services\Billing;

/**
 * Builds an invoice's itemized `lines` (each {description, amount_minor}) and
 * appends VAT on the pre-tax subtotal — the single place that computes VAT so
 * every invoice-issuing call site (seat purchases, dev/demo data) stays in sync.
 * All amounts are minor units (kobo).
 */
class InvoiceLineBuilder
{
    /** Nigeria's standard VAT rate. */
    public const VAT_RATE = 0.075;

    public const STUDENT_SCHOOL_FEES = 'Student School Fees';

    public const REGISTRATION_FEES = 'Registration Fees';

    /**
     * Build the canonical school invoice breakdown. Both fee categories remain
     * present even when registration is waived for a top-up.
     *
     * @return array{lines: list<array{description: string, amount_minor: int}>, total_minor: int}
     */
    public static function schoolFees(int $studentSchoolFeesMinor, int $registrationFeesMinor): array
    {
        return self::withVat([
            ['description' => self::STUDENT_SCHOOL_FEES, 'amount_minor' => $studentSchoolFeesMinor],
            ['description' => self::REGISTRATION_FEES, 'amount_minor' => $registrationFeesMinor],
        ]);
    }

    /**
     * Append a VAT line (7.5% of the subtotal) to a list of pre-tax lines.
     *
     * @param  list<array{description: string, amount_minor: int}>  $lines
     * @return array{lines: list<array{description: string, amount_minor: int}>, total_minor: int}
     */
    public static function withVat(array $lines): array
    {
        $subtotal = array_sum(array_column($lines, 'amount_minor'));
        $vat = (int) round($subtotal * self::VAT_RATE);

        $withVat = $lines;
        if ($vat > 0) {
            $withVat[] = ['description' => 'VAT (7.5%)', 'amount_minor' => $vat];
        }

        return ['lines' => $withVat, 'total_minor' => $subtotal + $vat];
    }
}
