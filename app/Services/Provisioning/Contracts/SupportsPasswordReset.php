<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;

/**
 * Capability: provider can reset the instance login password.
 * Implementations must honor the OS default user (Ubuntu images use
 * 'ubuntu', not root) and any reboot the reset triggers.
 */
interface SupportsPasswordReset
{
    /**
     * Reset the default user's password and wait until the instance is
     * usable again. Returns ['status' => string, 'ssh_username' => string].
     */
    public function resetPassword(VpsInstance $instance, ProvisioningTask $task, string $newPassword): array;
}