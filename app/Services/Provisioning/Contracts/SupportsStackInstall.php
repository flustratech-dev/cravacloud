<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;

/**
 * Capability: provider can install application stacks on a running instance
 * (TAT RunCommand for Tencent). Checked via instanceof so ManualProvider
 * instances simply never offer the feature — no changes to ManualProvider.
 */
interface SupportsStackInstall
{
    /**
     * Invoke the stack install. Returns ['invocation_id' => string, 'stack' => string].
     * The invocation id MUST be persisted by the caller before polling.
     */
    public function installStack(VpsInstance $instance, ProvisioningTask $task): array;

    /**
     * Poll the install result for the invocation recorded in $task->output.
     * Returns ['status' => 'running|success|failed', 'output' => ?string].
     * Each call is a single lightweight API round-trip (no long sleeps) —
     * the queue job backoff provides the waiting between attempts.
     */
    public function stackInstallResult(VpsInstance $instance, ProvisioningTask $task): array;
}