<?php

namespace App\Services\Provisioning\Tencent;

use App\Services\Provisioning\Exceptions\ProviderException;

/**
 * Maps Tencent Cloud API error codes to ProviderException with a correct
 * retryable flag.
 *
 * Conservative policy: UNKNOWN codes are NON-retryable by default. Retrying
 * an unknown failure of a billing operation (e.g. CreateInstances) could
 * double-purchase; the provider layer handles ambiguous outcomes via
 * checkpoints + orphan adoption instead of blind retries.
 */
class TencentErrorMapper
{
    /** Transient errors that are safe to retry via queue backoff. */
    private const RETRYABLE = [
        'RequestLimitExceeded',
        'InternalError',
        'FailedOperation.InstanceNotRunning',
        'FailedOperation.InvalidInstanceState',
        'InvalidInstanceState',
    ];

    private const RETRYABLE_PREFIXES = [
        'RequestLimitExceeded.',
        'InternalError.',
    ];

    /** Explicit non-retryable codes (documented for clarity; default is also false). */
    private const NON_RETRYABLE = [
        'FailedOperation.InsufficientBalance',
        'ResourceInsufficient',
        'ResourcesSoldOut',
        'ResourceUnavailable',
        'OperationDenied',
        'UnauthorizedOperation',
        'AuthFailure.SecretIdNotFound',
        'AuthFailure.SignatureFailure',
        'AuthFailure.SignatureExpire',
        'InvalidParameterValue',
        'InvalidParameter',
        'MissingParameter',
        'UnknownParameter',
        'InvalidRegion',
        'LimitExceeded',
        'QuotaLimitExceeded',
    ];

    public static function isRetryable(string $code): bool
    {
        if (in_array($code, self::RETRYABLE, true)) {
            return true;
        }

        foreach (self::RETRYABLE_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{Code?: string, Message?: string} $error
     */
    public static function toException(string $service, string $action, array $error, ?string $requestId = null): ProviderException
    {
        $code = (string) ($error['Code'] ?? 'UnknownError');
        $message = (string) ($error['Message'] ?? 'Unknown Tencent API error');

        return new ProviderException(
            "[{$service}/{$action}] {$code}: {$message}".($requestId ? " (RequestId: {$requestId})" : ''),
            self::isRetryable($code),
            $code,
            [
                'service' => $service,
                'action' => $action,
                'request_id' => $requestId,
                'error' => $error,
            ],
        );
    }
}