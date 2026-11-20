<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\VpsInstance;

/**
 * Capability: provider exposes monthly traffic-package usage (Tencent
 * DescribeInstancesTrafficPackages). Checked via instanceof so ManualProvider
 * never offers it. Implementations SHOULD cache briefly (~60s) to avoid
 * hammering the upstream API on dashboard refreshes.
 */
interface SupportsTrafficQuota
{
    /**
     * @return array{used_bytes: int, total_bytes: int, reset_at: ?string, percent: float}
     */
    public function trafficUsage(VpsInstance $instance): array;
}