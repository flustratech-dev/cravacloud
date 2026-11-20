<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Models\VpsSpec;

/**
 * Capability: provider can upgrade the instance bundle upstream (Tencent
 * ModifyInstancesBundle). Checked via instanceof so ManualProvider never
 * offers it.
 *
 * UPGRADE-ONLY: every target resource (cpu/ram/disk) must be >= the current
 * one — the upstream API rejects downgrades. Implementations MUST poll until
 * the mutation settles (SUCCESS && RUNNING) and re-sync the instance
 * resources from the target spec. Billing (prorata invoice, supplier
 * purchase, orders.vps_spec_id) is handled by the caller/job, not here.
 */
interface SupportsUpgrade
{
    /**
     * @return array{status: string, bundle_id: string, cpu: int, ram: int, disk: int}
     *
     * @throws \App\Services\Provisioning\Exceptions\ProviderException
     *         non-retryable 'DowngradeNotAllowed' when any target resource is
     *         smaller than the current one.
     */
    public function upgradeBundle(VpsInstance $instance, ProvisioningTask $task, VpsSpec $targetSpec): array;
}