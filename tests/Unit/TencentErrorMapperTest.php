<?php

namespace Tests\Unit;

use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\Tencent\TencentErrorMapper;
use PHPUnit\Framework\TestCase;

class TencentErrorMapperTest extends TestCase
{
    public function test_transient_errors_are_retryable(): void
    {
        foreach ([
            'RequestLimitExceeded',
            'RequestLimitExceeded.UinLimitExceeded',
            'InternalError',
            'InternalError.SomeSubCode',
            'FailedOperation.InstanceNotRunning',
            'FailedOperation.InvalidInstanceState',
            'InvalidInstanceState',
        ] as $code) {
            $this->assertTrue(TencentErrorMapper::isRetryable($code), "{$code} harus retryable");
        }
    }

    public function test_business_errors_are_not_retryable(): void
    {
        foreach ([
            'InsufficientBalance',
            'ResourceInsufficient',
            'ResourcesSoldOut',
            'ResourcesSoldOut.SpecifiedInstanceType',
            'OperationDenied',
            'AuthFailure.SignatureFailure',
            'AuthFailure.SecretIdNotFound',
            'InvalidParameter',
            'InvalidParameterValue',
            'UnsupportedOperation',
            'FailedOperation.BundleNotSupport',
        ] as $code) {
            $this->assertFalse(TencentErrorMapper::isRetryable($code), "{$code} harus NON-retryable");
        }
    }

    public function test_unknown_codes_default_to_non_retryable(): void
    {
        // Conservative default: an unknown failure on a billing-bearing call
        // must NOT trigger blind retries (anti double-purchase).
        $this->assertFalse(TencentErrorMapper::isRetryable('SomeBrandNewCode'));
        $this->assertFalse(TencentErrorMapper::isRetryable(''));
    }

    public function test_to_exception_preserves_context(): void
    {
        $e = TencentErrorMapper::toException('lighthouse', 'CreateInstances', [
            'Code' => 'ResourcesSoldOut',
            'Message' => 'The resource is sold out.',
        ], 'req-123');

        $this->assertInstanceOf(ProviderException::class, $e);
        $this->assertSame('ResourcesSoldOut', $e->providerCode);
        $this->assertFalse($e->retryable);
        $this->assertStringContainsString('[lighthouse/CreateInstances]', $e->getMessage());
        $this->assertStringContainsString('ResourcesSoldOut', $e->getMessage());
        $this->assertStringContainsString('req-123', $e->getMessage());
        $this->assertSame('lighthouse', $e->providerResponse['service']);
        $this->assertSame('CreateInstances', $e->providerResponse['action']);
        $this->assertSame('req-123', $e->providerResponse['request_id']);
    }

    public function test_to_exception_marks_retryable_codes(): void
    {
        $e = TencentErrorMapper::toException('tat', 'RunCommand', [
            'Code' => 'RequestLimitExceeded',
            'Message' => 'rate limited',
        ]);

        $this->assertTrue($e->retryable);
    }
}