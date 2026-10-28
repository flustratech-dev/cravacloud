<?php

namespace App\Services\Provisioning\Exceptions;

use RuntimeException;

/**
 * Exception dari provider operation. Set $retryable=true kalau
 * error transient (network hiccup) supaya queue job auto-retry.
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        public readonly ?string $providerCode = null,
        public readonly ?array $providerResponse = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
