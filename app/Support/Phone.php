<?php

namespace App\Support;

/**
 * Phone-number normalisation to a canonical `+<country><subscriber>` (E.164-ish)
 * string so uniqueness can be enforced regardless of how a caller typed it.
 *
 * We do not pull in a full libphonenumber dependency: the platform is
 * Nigeria-first with a diaspora tail, so we normalise the common shapes
 * (local `0…`, bare national, `+`/`00` international) and otherwise keep the
 * digits with a leading `+`.
 */
class Phone
{
    /** Default calling code when a bare national number is given with no context. */
    public const DEFAULT_DIAL_CODE = '234';

    /**
     * @param  string|null  $raw  what the user typed (any format)
     * @param  string|null  $dialCode  selected country calling code, e.g. "234" or "+234"
     */
    public static function normalize(?string $raw, ?string $dialCode = null): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $digits = ltrim($digits, '0'); // drop trunk/00 prefixes; we re-add country below

        if ($digits === '') {
            return null;
        }

        if ($international) {
            return strlen($digits) >= 8 ? '+'.$digits : null;
        }

        $cc = preg_replace('/\D+/', '', (string) ($dialCode ?: self::DEFAULT_DIAL_CODE)) ?: self::DEFAULT_DIAL_CODE;

        // Already carries its country code (e.g. "2348012345678").
        if (str_starts_with($digits, $cc) && strlen($digits) > strlen($cc) + 5) {
            return '+'.$digits;
        }

        $subscriber = strlen($digits) >= 7 ? $digits : null;

        return $subscriber === null ? null : '+'.$cc.$subscriber;
    }
}
