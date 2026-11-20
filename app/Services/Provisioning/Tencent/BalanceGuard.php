<?php

namespace App\Services\Provisioning\Tencent;

use App\Services\Provisioning\Exceptions\ProviderException;
use Illuminate\Support\Facades\Cache;

/**
 * Deposit Balance Guard (blueprint §2.5, D9).
 *
 * UNIT CAUTION: DescribeAccountBalance returns cents on international
 * accounts; divisor is configurable (TENCENT_BALANCE_DIVISOR, default 100).
 * The account balance was 0 at Phase 1 probe, so the unit is NOT yet
 * verified against a non-zero value — re-verify via tencent:probe --live
 * after top-up before trusting thresholds in production.
 *
 * Usage:
 *  - balanceUsd()          cached (60s) — for dashboards/cron.
 *  - assertSufficient(hpp) called at provisioning start (cached OK) and
 *    AGAIN with fresh: true immediately before CreateInstances (Phase 3).
 *    Insufficient → non-retryable ProviderException: the order fails safely,
 *    payment stays intact, admin alerts, retry-provision button reuses the
 *    existing failed→provisioning transition.
 */
class BalanceGuard
{
    private const CACHE_KEY = 'tencent:balance';

    public function __construct(private readonly BillingClient $billing)
    {
    }

    public function balanceUsd(bool $fresh = false): float
    {
        $ttl = (int) config('services.tencent.balance_cache_ttl', 60);

        $raw = $fresh
            ? $this->fetchRawBalance()
            : Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->fetchRawBalance());

        $divisor = max(1, (int) config('services.tencent.balance_divisor', 100));

        return round($raw / $divisor, 2);
    }

    /**
     * @throws ProviderException non-retryable when balance < required HPP.
     */
    public function assertSufficient(float $hppUsd, bool $fresh = false): void
    {
        $balance = $this->balanceUsd($fresh);

        if ($balance < $hppUsd) {
            throw new ProviderException(
                "Upstream balance insufficient: {$balance} USD < required {$hppUsd} USD. Top up the upstream account, then retry provisioning.",
                false,
                'InsufficientBalance',
                ['balance_usd' => $balance, 'required_usd' => $hppUsd],
            );
        }
    }

    public function lowBalanceThresholdUsd(): float
    {
        return (float) config('services.tencent.low_balance_usd', 20);
    }

    private function fetchRawBalance(): int|float
    {
        $resp = $this->billing->describeAccountBalance();

        return (float) ($resp['Balance'] ?? 0);
    }
}