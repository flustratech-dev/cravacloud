<?php

namespace App\Services\Provisioning\Exceptions;

use RuntimeException;

/**
 * Phase 5D — domain error for customer/admin Day-2 self-service actions.
 * Carries a stable machine-readable $errorCode and a white-label $message safe to
 * show the customer. Controllers translate it into a 422 JSON response.
 * $replacementSpecId is surfaced when an upgrade is rejected so the UI can
 * direct the customer to the recommended replacement plan (EOL).
 */
class Day2Exception extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'invalid',
        public readonly ?int $replacementSpecId = null,
    ) {
        parent::__construct($message);
    }
}