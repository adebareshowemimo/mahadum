<?php

namespace App\Services\Billing;

use Symfony\Component\HttpKernel\Exception\HttpException;

class MonnifyBillsRequestException extends HttpException
{
    public function __construct(
        public readonly string $endpoint,
        public readonly int $providerHttpStatus,
        public readonly ?string $providerCode,
        public readonly ?string $providerMessage,
        string $customerMessage,
        int $status = 502,
    ) {
        parent::__construct($status, $customerMessage);
    }
}
