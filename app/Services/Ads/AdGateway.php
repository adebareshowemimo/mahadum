<?php

namespace App\Services\Ads;

interface AdGateway
{
    /** Is a fillable ad currently available for this placement? */
    public function available(string $placement): bool;

    /**
     * Server-side verification that the ad referenced by `$adRef` played to
     * completion (for example, a stored SSV callback for a real network).
     * Verification must remain repeatable through completion and redemption.
     */
    public function verifyReward(string $adRef): bool;
}
