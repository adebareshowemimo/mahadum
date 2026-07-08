<?php

namespace App\Services\Ads;

interface AdGateway
{
    /** Is a fillable ad currently available for this placement? */
    public function available(string $placement): bool;

    /**
     * Server-side verification that the ad referenced by `$adRef` played to
     * completion (an SSV callback for a real network; deterministic for Null).
     */
    public function verifyReward(string $adRef): bool;
}
