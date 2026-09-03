<?php

namespace App\Services\Referral;

use RuntimeException;

/**
 * Thrown by ReferralService::invite() when the invited email / phone already
 * belongs to an active account — a user can't refer someone who's already on the
 * platform. The controller turns this into a 422 `account_exists`.
 */
class ReferralAccountExistsException extends RuntimeException
{
    public function __construct(string $message = 'This account already exists and can\'t be referred.')
    {
        parent::__construct($message);
    }
}
