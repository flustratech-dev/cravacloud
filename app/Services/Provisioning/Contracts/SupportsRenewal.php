<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;

/**
 * Capability: provider can renew the prepaid period upstream (RenewInstances
 * for Tencent). Checked via instanceof so ManualProvider never offers it.
 *
 * Revisi 4 (blueprint): RenewInstances is ALSO the only recovery path for a
 * SHUTDOWN (recycle bin) instance — StartInstances does not work there. The
 * implementation MUST poll until RUNNING and re-sync public/private IPs,
 * because recycle-bin recovery may assign a NEW public IP.
 */
interface SupportsRenewal
{
    /**
     * Renew $months prepaid months upstream; recover SHUTDOWN instances and
     * start STOPPED ones. Returns at least:
     *   ['status' => 'running', 'resource_id' => string,
     *    'public_ip' => ?string, 'private_ip' => ?string,
     *    'meta' => ['upstream_expired_time' => ?string, ...]]
     *
     * @throws \App\Services\Provisioning\Exceptions\ProviderException
     *         non-retryable 'InsufficientBalance' when the upstream deposit
     *         cannot cover the renewal (customer period stays intact).
     */
    public function renew(VpsInstance $instance, ProvisioningTask $task, int $months): array;
}