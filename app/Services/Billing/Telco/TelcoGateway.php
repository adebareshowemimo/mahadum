<?php

namespace App\Services\Billing\Telco;

/**
 * Operator SDP (airtime VAS) gateway: charge a daily fee and deliver enrolment
 * OTPs over SMS. Implementations are swappable and resolved by
 * TelcoGatewayManager; the inbound DLR webhook (TelcoWebhookController) confirms
 * async charge results out of band.
 */
interface TelcoGateway
{
    /**
     * @param  string  $reference  our correlation key for this attempt
     */
    public function charge(string $msisdn, string $operator, int $amountMinor, string $reference): TelcoChargeResult;

    /** Deliver a one-time enrolment code to the MSISDN via the operator SDP (SMS). */
    public function sendOtp(string $msisdn, string $operator, string $code): void;
}
