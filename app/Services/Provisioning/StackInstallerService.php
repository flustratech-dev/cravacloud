<?php

namespace App\Services\Provisioning;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\Stacks\StackDefinition;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\Provisioning\Stacks\StackScriptBuilder;
use App\Services\Provisioning\Tencent\ResolvesRegion;
use App\Services\Provisioning\Tencent\TatClient;
use RuntimeException;

/**
 * 1-Click Auto-Install engine (blueprint §2.3). Assembles the idempotent
 * bash script for a stack, invokes it via TAT RunCommand, and polls the
 * invocation result with single lightweight round-trips.
 *
 * D6: no plaintext secrets travel through TAT for the Phase 3 stacks —
 * panels (Coolify/CloudPanel) use first-run setup, and the root password
 * is delivered exclusively via ResetInstancesPassword.
 */
class StackInstallerService
{
    use ResolvesRegion;

    private readonly StackScriptBuilder $builder;

    public function __construct(
        private readonly TatClient $tat,
        private readonly StackRegistry $registry,
        ?StackScriptBuilder $builder = null,
    ) {
        $this->builder = $builder ?? new StackScriptBuilder();
    }

    public function registry(): StackRegistry
    {
        return $this->registry;
    }

    /**
     * Invoke the install script. Returns ['invocation_id' => ..., 'stack' => ...].
     * Caller MUST persist the invocation id immediately (checkpoint).
     */
    public function invoke(VpsInstance $instance, ProvisioningTask $task, StackDefinition $definition, ?string $region = null): array
    {
        $region ??= $this->regionFor($instance);
        $script = $this->renderScript($definition, $instance);

        $resp = $this->tat->runCommand([
            'CommandName' => 'crava-install-'.$definition->key.'-'.$instance->order_id,
            'Content' => base64_encode($script),
            'CommandType' => 'SHELL',
            'Timeout' => 3600,
            'WorkingDirectory' => '/root',
            'InstanceIds' => [$instance->provider_resource_id],
        ], $region);

        $invocationId = $resp['InvocationId'] ?? null;
        if (! $invocationId) {
            throw new ProviderException('RunCommand returned no InvocationId', true, 'MissingInvocationId');
        }

        return ['invocation_id' => $invocationId, 'stack' => $definition->key];
    }

    /**
     * Single lightweight poll. Returns ['status' => 'running|success|failed', 'output' => ?string].
     */
    public function result(string $invocationId, string $upstreamInstanceId, ?string $region = null): array
    {
        $resp = $this->tat->describeInvocationTasks($invocationId, $upstreamInstanceId, $region);
        $info = $resp['InvocationTaskBasicInfoSet'][0] ?? null;

        if (! $info) {
            return ['status' => 'running', 'output' => null]; // not visible yet
        }

        $status = match (strtoupper((string) ($info['TaskStatus'] ?? ''))) {
            'SUCCESS' => 'success',
            'FAILED', 'TIMEOUT' => 'failed',
            default => 'running',
        };

        $output = null;
        if (! empty($info['TaskResult'])) {
            $decoded = base64_decode((string) $info['TaskResult'], true);
            if ($decoded !== false) {
                $output = mb_substr($decoded, -4000);
            }
        }

        return ['status' => $status, 'output' => $output];
    }

    /**
     * Load the bash template, inject _common.sh helpers, and substitute non-secret placeholders only.
     */
    public function renderScript(StackDefinition $definition, VpsInstance $instance): string
    {
        return $this->builder->build($definition, $instance);
    }
}