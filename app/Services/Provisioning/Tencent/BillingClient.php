<?php

namespace App\Services\Provisioning\Tencent;

/**
 * Tencent Cloud Billing API (version 2018-07-09, international endpoint).
 * Region-less: no X-TC-Region header is sent (handled by TencentApiClient).
 *
 * NOTE: the unit of the returned Balance field (cents vs dollars) MUST be
 * verified via `php artisan tencent:probe --live` before any threshold logic
 * is built on top of it (Phase 2 BalanceGuard).
 */
class BillingClient
{
    public function __construct(private readonly TencentApiClient $api)
    {
    }

    public function describeAccountBalance(): array
    {
        return $this->api->call('billing', 'DescribeAccountBalance');
    }
}