<?php

namespace App\Services\Provisioning\Providers;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\Provisioning\Contracts\SupportsFirewall;
use App\Services\Provisioning\Contracts\SupportsPasswordReset;
use App\Services\Provisioning\Contracts\SupportsRenewal;
use App\Services\Provisioning\Contracts\SupportsStackInstall;
use App\Services\Provisioning\Contracts\SupportsTrafficQuota;
use App\Services\Provisioning\Contracts\SupportsUpgrade;
use App\Services\Provisioning\Contracts\SupportsVncConsole;
use App\Services\Provisioning\Contracts\VpsProviderContract;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\StackInstallerService;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\Provisioning\Tencent\BalanceGuard;
use App\Services\Provisioning\Tencent\LighthouseClient;
use App\Services\Provisioning\Tencent\ResolvesRegion;
use App\Services\Provisioning\Tencent\TatClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tencent Cloud Lighthouse provider (INTERNAL driver name 'tencent' —
 * white-label: customer-facing output never mentions the vendor; the
 * public label stays 'cravacloud' via Order::customerProviderLabel()).
 *
 * Idempotency, 3 layers (blueprint §2.2 — CreateInstances has NO ClientToken):
 *  1. Persistent per-step checkpoint in task.output['checkpoint'], written
 *     IMMEDIATELY after each step succeeds.
 *  2. Resume, never redo: when instance.provider_resource_id or
 *     task.output['instance_id'] exists, CreateInstances is skipped and the
 *     flow continues after the last checkpoint.
 *  3. Orphan adoption: before any create, DescribeInstances with the official
 *     `instance-name` filter looks for the deterministic name
 *     crava-{order_id}-{instance_id}; a found instance is adopted instead of
 *     buying a second one (covers ambiguous timeout/network failures).
 *
 * Safe ordering (revisions 2 & 3): create → RUNNING → ResetInstancesPassword
 * (triggers reboot) → poll LatestOperationState=SUCCESS && RUNNING → TAT agent
 * Online → firewall diff → RunCommand.
 */
class TencentLighthouseProvider implements VpsProviderContract, SupportsStackInstall, SupportsPasswordReset, SupportsFirewall, SupportsRenewal, SupportsVncConsole, SupportsTrafficQuota, SupportsUpgrade
{
    use ResolvesRegion;

    /** Ordered provisioning checkpoints (blueprint §2.2). */
    private const CHECKPOINTS = [
        'created', 'running', 'password_set', 'operation_success',
        'agent_online', 'firewall', 'tat_invoked', 'tat_done',
    ];

    /** Ordered reinstall checkpoints (Phase 5B — mirrors provision steps 3–7). */
    private const REINSTALL_CHECKPOINTS = [
        'reinstall_reset', 'reinstall_password', 'reinstall_operation',
        'reinstall_agent', 'reinstall_firewall', 'reinstall_tat', 'reinstall_done',
    ];

    public function __construct(
        private readonly LighthouseClient $lighthouse,
        private readonly TatClient $tat,
        private readonly BalanceGuard $balanceGuard,
        private readonly StackInstallerService $installer,
        private readonly StackRegistry $stacks,
    ) {
    }

    public function name(): string
    {
        return 'tencent';
    }

    // =====================================================================
    // Provision (infra stage — stage 1 of the D11 two-stage job design)
    // =====================================================================

    public function provision(VpsInstance $instance, ProvisioningTask $task): array
    {
        $order = $instance->order;
        $spec = $order?->vpsSpec;

        if (! $spec || ! $spec->hasUpstreamMapping()) {
            throw new ProviderException('Spec has no upstream mapping (D3 hold).', false, 'NoUpstreamMapping');
        }

        $region = $this->regionFor($instance);
        $bundleId = (string) $spec->upstream_bundle_id;
        $stackKey = (string) ($order->control_panel ?: 'none');
        $definition = $this->stacks->get($stackKey);

        if (! $definition) {
            throw new ProviderException("Stack [{$stackKey}] is not auto-installable.", false, 'UnsupportedStack');
        }

        if (! $definition->supportsOs((string) $order->os)) {
            throw new ProviderException("OS [{$order->os}] not supported for stack [{$stackKey}].", false, 'UnsupportedOsStack');
        }

        $blueprintId = $this->stacks->blueprintIdFor((string) $order->os, $stackKey);
        if (! $blueprintId) {
            throw new ProviderException('OS has no verified blueprint (D2).', false, 'UnsupportedOs');
        }

        $task->markStarted('Menyiapkan server');

        $output = $task->output ?? [];
        $checkpoint = $output['checkpoint'] ?? null;
        $instanceId = $instance->provider_resource_id ?: ($output['instance_id'] ?? null);

        // --- Step 1: create or adopt (skipped entirely on resume) ---------
        if (! $instanceId) {
            // Fresh balance check right before money is spent (D9).
            $hppUsd = (float) config("services.tencent.bundle_prices_usd.{$bundleId}", 0);
            $this->balanceGuard->assertSufficient($hppUsd, fresh: true);

            $instanceId = $this->createOrAdopt($instance, $region, $bundleId, $blueprintId);

            // Persist IMMEDIATELY — before any further step can fail.
            $instance->provider_resource_id = $instanceId;
            $instance->save();

            $output['instance_id'] = $instanceId;
            $checkpoint = 'created';
            $this->checkpoint($task, $output, $checkpoint, 10, 'Server dibuat');
        }

        // --- Step 2: wait RUNNING -----------------------------------------
        if (! $this->reached($checkpoint, 'running')) {
            $remote = $this->waitInstance($instanceId, $region, $task,
                fn (array $i) => ($i['InstanceState'] ?? '') === 'RUNNING',
                'Menunggu server aktif');
            $this->fillAddresses($instance, $remote);
            $checkpoint = 'running';
            $this->checkpoint($task, $output, $checkpoint, 30, 'Server aktif');
        }

        // --- Step 3: reset default user's password (triggers reboot) ------
        if (! $this->reached($checkpoint, 'password_set')) {
            $password = $this->generatePassword();
            $userName = $this->defaultUserFor((string) $order->os);

            $this->lighthouse->resetInstancesPassword([$instanceId], $password, $region, $userName);

            // Persist credentials immediately (encrypted at rest via casts).
            $instance->initial_root_password = $password;
            $instance->ssh_username = $userName;
            $instance->save();

            $checkpoint = 'password_set';
            $this->checkpoint($task, $output, $checkpoint, 45, 'Menyiapkan kredensial');
        }

        // --- Step 4: wait reset operation SUCCESS && RUNNING --------------
        if (! $this->reached($checkpoint, 'operation_success')) {
            $remote = $this->waitInstance($instanceId, $region, $task,
                fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                    && ($i['InstanceState'] ?? '') === 'RUNNING'
                    && ($i['LatestOperation'] ?? '') === 'ResetInstancesPassword',
                'Menyiapkan kredensial');
            $this->fillAddresses($instance, $remote);
            $checkpoint = 'operation_success';
            $this->checkpoint($task, $output, $checkpoint, 50, 'Kredensial siap');
        }

        // --- Step 5: TAT agent Online gate (only when a stack install follows)
        if ($definition->needsInstall() && ! $this->reached($checkpoint, 'agent_online')) {
            $this->waitAgentOnline($instanceId, $region, $task);
            $checkpoint = 'agent_online';
            $this->checkpoint($task, $output, $checkpoint, 55, 'Server siap dikonfigurasi');
        }

        // --- Step 6: firewall diff (revision 5 — never duplicate defaults) -
        if (! $this->reached($checkpoint, 'firewall')) {
            $this->openPorts($instanceId, $definition->ports, $region, $stackKey);
            $checkpoint = 'firewall';
            $this->checkpoint($task, $output, $checkpoint, 60, 'Mengamankan jaringan');
        }

        // --- Step 7: invoke stack install via TAT --------------------------
        $invocationId = $output['invocation_id'] ?? null;
        if ($definition->needsInstall()) {
            if (! $this->reached($checkpoint, 'tat_invoked')) {
                $result = $this->installer->invoke($instance, $task, $definition, $region);
                $invocationId = $result['invocation_id'];
                $output['invocation_id'] = $invocationId;
                $checkpoint = 'tat_invoked';
                $this->checkpoint($task, $output, $checkpoint, 65, 'Memasang stack aplikasi');
            }
        } else {
            $checkpoint = 'tat_done';
            $this->checkpoint($task, $output, $checkpoint, 90, 'Server siap');
        }

        return [
            'resource_id' => $instanceId,
            'instance_id' => $instanceId,
            'public_ip' => $instance->public_ip,
            'private_ip' => $instance->private_ip,
            'ssh_port' => 22,
            // Credentials are NEVER copied into task.output (plaintext JSON):
            // they live encrypted on the instance (initial_root_password cast).
            'ssh_username' => $instance->ssh_username,
            'meta' => [
                'provider' => 'tencent',
                'region' => $region,
                'bundle_id' => $bundleId,
                'blueprint_id' => $blueprintId,
                'stack' => $stackKey,
            ],
            'checkpoint' => $checkpoint,
            'invocation_id' => $invocationId,
            'stack_install_pending' => $definition->needsInstall(),
        ];
    }

    // =====================================================================
    // SupportsStackInstall
    // =====================================================================

    public function installStack(VpsInstance $instance, ProvisioningTask $task): array
    {
        $stackKey = (string) ($task->input['stack'] ?? $instance->control_panel ?: 'none');
        $definition = $this->stacks->get($stackKey);

        if (! $definition || ! $definition->needsInstall()) {
            throw new ProviderException("Stack [{$stackKey}] has no install script.", false, 'UnsupportedStack');
        }

        return $this->installer->invoke($instance, $task, $definition, $this->regionFor($instance));
    }

    public function stackInstallResult(VpsInstance $instance, ProvisioningTask $task): array
    {
        $invocationId = $task->output['invocation_id'] ?? $task->input['invocation_id'] ?? null;
        if (! $invocationId) {
            throw new ProviderException('No invocation id recorded for install task.', false, 'MissingInvocationId');
        }

        return $this->installer->result(
            (string) $invocationId,
            (string) $instance->provider_resource_id,
            $this->regionFor($instance),
        );
    }

    // =====================================================================
    // SupportsPasswordReset
    // =====================================================================

    public function resetPassword(VpsInstance $instance, ProvisioningTask $task, string $newPassword): array
    {
        $instanceId = (string) $instance->provider_resource_id;
        $region = $this->regionFor($instance);
        $userName = $this->defaultUserFor((string) $instance->os);

        $task->markStarted('Mereset kredensial server');
        $this->lighthouse->resetInstancesPassword([$instanceId], $newPassword, $region, $userName);

        $this->waitInstance($instanceId, $region, $task,
            fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                && ($i['InstanceState'] ?? '') === 'RUNNING'
                && ($i['LatestOperation'] ?? '') === 'ResetInstancesPassword',
            'Menerapkan kredensial baru');

        $instance->initial_root_password = $newPassword;
        $instance->ssh_username = $userName;
        $instance->save();

        return ['status' => 'running', 'ssh_username' => $userName];
    }

    // =====================================================================
    // SupportsFirewall
    // =====================================================================

    public function listFirewallRules(VpsInstance $instance): array
    {
        $demo = $this->localDemo('firewall', $instance);
        if ($demo !== null) {
            return $demo;
        }

        $resp = $this->lighthouse->describeFirewallRules(
            (string) $instance->provider_resource_id,
            $this->regionFor($instance),
        );

        return $resp['FirewallRuleSet'] ?? [];
    }

    public function addFirewallRules(VpsInstance $instance, array $rules): array
    {
        $instanceId = (string) $instance->provider_resource_id;
        $region = $this->regionFor($instance);

        $existing = $this->listFirewallRules($instance);
        $missing = $this->diffRules($existing, $rules);

        if ($missing !== []) {
            // Local preview: mutate the demo store instead of calling upstream.
            if ($this->localDemo('firewall', $instance) !== null) {
                Cache::forever('tencent:demo:firewall:'.$instance->id, array_values(array_merge($existing, $missing)));
            } else {
                $this->lighthouse->createFirewallRules($instanceId, $missing, $region);
            }
        }

        return ['created' => count($missing)];
    }

    public function deleteFirewallRules(VpsInstance $instance, array $rules): array
    {
        // Local preview: remove from the demo store instead of calling upstream.
        if ($this->localDemo('firewall', $instance) !== null) {
            $existing = $this->listFirewallRules($instance);
            $dropKeys = array_map(fn ($r) => $this->ruleKey($r), $rules);
            $kept = array_values(array_filter($existing, fn ($r) => !in_array($this->ruleKey($r), $dropKeys, true)));
            Cache::forever('tencent:demo:firewall:'.$instance->id, $kept);

            return ['deleted' => count($rules)];
        }

        $this->lighthouse->deleteFirewallRules(
            (string) $instance->provider_resource_id,
            $rules,
            $this->regionFor($instance),
        );

        return ['deleted' => count($rules)];
    }

    /**
     * Local-only demo data for the Rp0 browser preview (DemoTencentUiSeeder).
     * Returns null unless APP_ENV=local AND the seeder populated the cache key,
     * so production and the test suite (testing env) never take this branch.
     */
    private function localDemo(string $kind, VpsInstance $instance): ?array
    {
        if (!app()->environment('local')) {
            return null;
        }
        $key = 'tencent:demo:'.$kind.':'.$instance->id;

        return Cache::has($key) ? Cache::get($key) : null;
    }

    /** Normalized diff key for a firewall rule (protocol|port|cidr). */
    private function ruleKey(array $rule): string
    {
        return strtoupper((string) ($rule['Protocol'] ?? '')).'|'.((string) ($rule['Port'] ?? '')).'|'.((string) ($rule['CidrBlock'] ?? ''));
    }

    // =====================================================================
    // SupportsVncConsole (Phase 5B — on-demand, single-use, NEVER cached)
    // =====================================================================

    public function vncUrl(VpsInstance $instance): array
    {
        // RUNNING guard uses the local state so each console request costs
        // exactly ONE upstream call (DescribeInstanceVncUrl). The controller
        // re-validates before dispatch.
        if ($instance->status !== 'running') {
            throw new ProviderException('Console requires a running server.', false, 'InstanceNotRunning');
        }

        $demo = $this->localDemo('vnc', $instance);
        if ($demo !== null) {
            return $demo;
        }

        $resp = $this->lighthouse->describeInstanceVncUrl(
            (string) $instance->provider_resource_id,
            $this->regionFor($instance),
        );

        $url = (string) ($resp['InstanceVncUrl'] ?? '');
        if ($url === '') {
            throw new ProviderException('Upstream returned no console URL.', true, 'MissingVncUrl');
        }

        // The URL is single-use (~15s). NEVER log, cache, or persist it.
        return ['url' => $url, 'expires_in' => 15];
    }

    // =====================================================================
    // SupportsTrafficQuota (Phase 5B — 60s cache)
    // =====================================================================

    public function trafficUsage(VpsInstance $instance): array
    {
        $demo = $this->localDemo('traffic', $instance);
        if ($demo !== null) {
            return $demo;
        }

        $key = 'tencent:traffic:'.$instance->id;

        return Cache::remember($key, 60, function () use ($instance) {
            $resp = $this->lighthouse->describeInstancesTrafficPackages(
                [(string) $instance->provider_resource_id],
                $this->regionFor($instance),
            );

            $packages = $resp['InstanceTrafficPackageSet'][0]['TrafficPackageSet'] ?? [];

            $used = 0;
            $total = 0;
            $resetAt = null;
            foreach ($packages as $pkg) {
                $used += (int) ($pkg['TrafficUsed'] ?? 0);
                $total += (int) ($pkg['TrafficPackageTotal'] ?? 0);
                $deadline = $pkg['Deadline'] ?? null;
                if ($deadline && ($resetAt === null || strtotime($deadline) > strtotime($resetAt))) {
                    $resetAt = $deadline;
                }
            }

            return [
                'used_bytes' => $used,
                'total_bytes' => $total,
                'reset_at' => $resetAt,
                'percent' => $total > 0 ? round($used / $total * 100, 2) : 0.0,
            ];
        });
    }

    // =====================================================================
    // SupportsUpgrade (Phase 5B — upgrade-only, poll, re-sync resources)
    // =====================================================================

    public function upgradeBundle(VpsInstance $instance, ProvisioningTask $task, VpsSpec $targetSpec): array
    {
        $instanceId = (string) $instance->provider_resource_id;
        $region = $this->regionFor($instance);

        if (! $targetSpec->hasUpstreamMapping()) {
            throw new ProviderException('Target spec has no upstream mapping (D3 hold).', false, 'NoUpstreamMapping');
        }

        // Upgrade-only: every resource must be >= current (API rejects downgrade).
        if ($targetSpec->cpu < $instance->cpu
            || $targetSpec->ram < $instance->ram
            || $targetSpec->disk < $instance->disk) {
            throw new ProviderException('Downgrade is not supported — choose a larger plan.', false, 'DowngradeNotAllowed');
        }

        $bundleId = (string) $targetSpec->upstream_bundle_id;
        $task->markStarted('Meningkatkan spesifikasi server');

        $this->lighthouse->modifyInstancesBundle([$instanceId], $bundleId, $region);

        $this->waitInstance($instanceId, $region, $task,
            fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                && ($i['InstanceState'] ?? '') === 'RUNNING',
            'Meningkatkan spesifikasi server');

        // Re-sync resources from the target spec (upstream reality). Billing
        // (orders.vps_spec_id, SupplierPurchase) is handled by UpgradeBundleJob.
        $instance->cpu = $targetSpec->cpu;
        $instance->ram = $targetSpec->ram;
        $instance->disk = $targetSpec->disk;
        $meta = $instance->provider_meta ?? [];
        $meta['bundle_id'] = $bundleId;
        $instance->provider_meta = $meta;
        $instance->save();

        return [
            'status' => 'running',
            'bundle_id' => $bundleId,
            'cpu' => (int) $targetSpec->cpu,
            'ram' => (int) $targetSpec->ram,
            'disk' => (int) $targetSpec->disk,
        ];
    }

    // =====================================================================
    // Power & lifecycle (contract; wired to jobs in Phase 4)
    // =====================================================================

    public function start(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Menyalakan server');
        $region = $this->regionFor($instance);
        $instanceId = (string) $instance->provider_resource_id;
        $this->lighthouse->startInstances([$instanceId], $region);

        // Bounded poll: confirm the mutation settled before we declare running
        // (consistent with reinstall/renew). LatestOperationState=SUCCESS &&
        // InstanceState=RUNNING.
        $this->waitInstance($instanceId, $region, $task,
            fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                && ($i['InstanceState'] ?? '') === 'RUNNING',
            'Menyalakan server');

        return ['status' => 'running'];
    }

    public function stop(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Mematikan server');
        $region = $this->regionFor($instance);
        $instanceId = (string) $instance->provider_resource_id;
        $this->lighthouse->stopInstances([$instanceId], $region);

        $this->waitInstance($instanceId, $region, $task,
            fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                && ($i['InstanceState'] ?? '') === 'STOPPED',
            'Mematikan server');

        return ['status' => 'stopped'];
    }

    public function reboot(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Memulai ulang server');
        $region = $this->regionFor($instance);
        $instanceId = (string) $instance->provider_resource_id;
        $this->lighthouse->rebootInstances([$instanceId], $region);

        $this->waitInstance($instanceId, $region, $task,
            fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                && ($i['InstanceState'] ?? '') === 'RUNNING',
            'Memulai ulang server');

        return ['status' => 'running'];
    }

    public function forceReboot(VpsInstance $instance, ProvisioningTask $task): array
    {
        return $this->reboot($instance, $task);
    }

    public function reinstall(VpsInstance $instance, ProvisioningTask $task): array
    {
        $input = $task->input ?? [];
        $os = (string) ($input['os'] ?? $instance->os);
        $stackKey = (string) ($input['control_panel'] ?? $instance->control_panel ?: 'none');
        $definition = $this->stacks->get($stackKey);

        if (! $definition || ! $definition->supportsOs($os)) {
            throw new ProviderException("OS [{$os}] not supported for stack [{$stackKey}].", false, 'UnsupportedOsStack');
        }

        $blueprintId = $this->stacks->blueprintIdFor($os, $stackKey);

        if (! $blueprintId) {
            throw new ProviderException('OS has no verified blueprint (D2).', false, 'UnsupportedOs');
        }

        $instanceId = (string) $instance->provider_resource_id;
        $region = $this->regionFor($instance);
        $task->markStarted('Memasang ulang sistem operasi');

        $output = $task->output ?? [];
        $checkpoint = $output['checkpoint'] ?? null;
        $seq = self::REINSTALL_CHECKPOINTS;

        // --- Step 1: ResetInstance (SINGULAR) → wait SUCCESS && RUNNING -----
        if (! $this->reached($checkpoint, 'reinstall_reset', $seq)) {
            $this->lighthouse->resetInstance($instanceId, $blueprintId, $region);
            $remote = $this->waitInstance($instanceId, $region, $task,
                fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                    && ($i['InstanceState'] ?? '') === 'RUNNING',
                'Memasang ulang sistem operasi');
            $this->fillAddresses($instance, $remote);
            $checkpoint = 'reinstall_reset';
            $this->checkpoint($task, $output, $checkpoint, 40, 'Sistem operasi terpasang');
        }

        // --- Step 2: reset the NEW OS default user's password (reboots) -----
        if (! $this->reached($checkpoint, 'reinstall_password', $seq)) {
            $password = $this->generatePassword();
            $userName = $this->defaultUserFor($os);

            $this->lighthouse->resetInstancesPassword([$instanceId], $password, $region, $userName);

            // Encrypted at rest via casts; NEVER copied into task.output.
            $instance->initial_root_password = $password;
            $instance->ssh_username = $userName;
            $instance->save();

            $checkpoint = 'reinstall_password';
            $this->checkpoint($task, $output, $checkpoint, 55, 'Menyiapkan kredensial');
        }

        // --- Step 3: wait reset operation SUCCESS && RUNNING ----------------
        if (! $this->reached($checkpoint, 'reinstall_operation', $seq)) {
            $remote = $this->waitInstance($instanceId, $region, $task,
                fn (array $i) => ($i['LatestOperationState'] ?? '') === 'SUCCESS'
                    && ($i['InstanceState'] ?? '') === 'RUNNING'
                    && ($i['LatestOperation'] ?? '') === 'ResetInstancesPassword',
                'Menyiapkan kredensial');
            $this->fillAddresses($instance, $remote);
            $checkpoint = 'reinstall_operation';
            $this->checkpoint($task, $output, $checkpoint, 65, 'Kredensial siap');
        }

        // --- Step 4: TAT agent Online gate (only when a stack install follows)
        if ($definition->needsInstall() && ! $this->reached($checkpoint, 'reinstall_agent', $seq)) {
            $this->waitAgentOnline($instanceId, $region, $task);
            $checkpoint = 'reinstall_agent';
            $this->checkpoint($task, $output, $checkpoint, 70, 'Server siap dikonfigurasi');
        }

        // --- Step 5: firewall diff for the stack (never duplicate defaults) -
        if (! $this->reached($checkpoint, 'reinstall_firewall', $seq)) {
            $this->openPorts($instanceId, $definition->ports, $region, $stackKey);
            $checkpoint = 'reinstall_firewall';
            $this->checkpoint($task, $output, $checkpoint, 80, 'Mengamankan jaringan');
        }

        // --- Step 6: invoke stack install via TAT (handoff to InstallStackJob)
        $invocationId = $output['invocation_id'] ?? null;
        if ($definition->needsInstall()) {
            if (! $this->reached($checkpoint, 'reinstall_tat', $seq)) {
                $result = $this->installer->invoke($instance, $task, $definition, $region);
                $invocationId = $result['invocation_id'];
                $output['invocation_id'] = $invocationId;
                $checkpoint = 'reinstall_tat';
                $this->checkpoint($task, $output, $checkpoint, 85, 'Memasang stack aplikasi');
            }
        } else {
            $checkpoint = 'reinstall_done';
            $this->checkpoint($task, $output, $checkpoint, 90, 'Server siap');
        }

        return [
            'status' => 'running',
            'os' => $os,
            'control_panel' => $stackKey,
            'ssh_username' => $instance->ssh_username,
            'invocation_id' => $invocationId,
            'stack_install_pending' => $definition->needsInstall(),
            'checkpoint' => $checkpoint,
        ];
    }

    // =====================================================================
    // SupportsRenewal (Phase 4B — renew + recycle-bin recovery, Revisi 4)
    // =====================================================================

    public function renew(VpsInstance $instance, ProvisioningTask $task, int $months): array
    {
        $instanceId = (string) $instance->provider_resource_id;
        if ($instanceId === '') {
            throw new ProviderException('Instance has no upstream resource id.', false, 'MissingResourceId');
        }

        $region = $this->regionFor($instance);
        $months = max(1, $months);

        $task->markStarted('Memperpanjang masa aktif server');

        $output = $task->output ?? [];
        $remote = $this->describe($instanceId, $region);
        $state = strtoupper((string) ($remote['InstanceState'] ?? ''));

        if ($state === 'TERMINATED') {
            throw new ProviderException('Upstream instance already terminated — renewal impossible.', false, 'InstanceTerminated');
        }

        // Idempotency (RenewInstances is NON-idempotent, no ClientToken):
        // persist the upstream ExpiredTime BEFORE invoking; on retry, an
        // already-extended ExpiredTime proves the previous attempt succeeded
        // upstream (even when the HTTP response was lost) → skip the call.
        $before = (string) ($output['renew_before_expired_time'] ?? '');
        $currentExpired = (string) ($remote['ExpiredTime'] ?? '');
        $alreadyRenewed = $before !== '' && $currentExpired !== ''
            && strtotime($currentExpired) > strtotime($before);

        if (! $alreadyRenewed) {
            if (! array_key_exists('renew_before_expired_time', $output)) {
                $output['renew_before_expired_time'] = $currentExpired;
                $output['renew_months'] = $months;
                $task->update(['output' => $output]);
            }

            // Fresh balance check right before money is spent (D9).
            $bundleId = (string) ($instance->provider_meta['bundle_id']
                ?? $instance->order?->vpsSpec?->upstream_bundle_id ?? '');
            $hppUsd = (float) config("services.tencent.bundle_prices_usd.{$bundleId}", 0) * $months;
            $this->balanceGuard->assertSufficient($hppUsd, fresh: true);

            $this->lighthouse->renewInstances([$instanceId], $months, 'NOTIFY_AND_MANUAL_RENEW', $region);

            $output['renew_invoked'] = true;
            $task->update(['output' => $output]);
        }

        // RenewInstances is ALSO the recycle-bin recovery path (Revisi 4):
        // SHUTDOWN → RECOVERING → RUNNING. Poll until settled; a merely
        // STOPPED instance (suspended via StopInstances) needs an explicit
        // StartInstances afterwards.
        $remote = $this->waitInstance($instanceId, $region, $task,
            fn (array $i) => in_array($i['InstanceState'] ?? '', ['RUNNING', 'STOPPED'], true),
            'Memulihkan server');

        if (($remote['InstanceState'] ?? '') === 'STOPPED') {
            $this->lighthouse->startInstances([$instanceId], $region);
            $remote = $this->waitInstance($instanceId, $region, $task,
                fn (array $i) => ($i['InstanceState'] ?? '') === 'RUNNING',
                'Menyalakan server');
        }

        // Re-sync addresses — recycle-bin recovery MAY assign a new public IP.
        $this->fillAddresses($instance, $remote);

        return [
            'status' => 'running',
            'resource_id' => $instanceId,
            'public_ip' => $instance->public_ip,
            'private_ip' => $instance->private_ip,
            'meta' => [
                'renewed_months' => $months,
                'upstream_expired_time' => $remote['ExpiredTime'] ?? null,
            ],
        ];
    }

    public function destroy(VpsInstance $instance, ProvisioningTask $task): array
    {
        $instanceId = (string) $instance->provider_resource_id;
        $region = $this->regionFor($instance);

        $task->markStarted('Menghapus server');

        $remote = $this->describe($instanceId, $region);
        $state = strtoupper((string) ($remote['InstanceState'] ?? ''));

        // Two-stage mandatory: Isolate (recycle bin) before Terminate.
        if ($state !== 'SHUTDOWN' && $state !== 'TERMINATED') {
            $this->lighthouse->isolateInstances([$instanceId], $region);
            $this->waitInstance($instanceId, $region, $task,
                fn (array $i) => ($i['InstanceState'] ?? '') === 'SHUTDOWN',
                'Menghapus server');
        }

        if ($state !== 'TERMINATED') {
            $this->lighthouse->terminateInstances([$instanceId], $region);
        }

        return ['status' => 'destroyed'];
    }

    public function status(VpsInstance $instance): array
    {
        $remote = $this->describe((string) $instance->provider_resource_id, $this->regionFor($instance));

        return [
            'status' => $this->mapState((string) ($remote['InstanceState'] ?? '')),
            'meta' => [
                'public_ip' => $remote['PublicAddresses'][0] ?? null,
                'private_ip' => $remote['PrivateAddresses'][0] ?? null,
                'upstream_state' => $remote['InstanceState'] ?? null,
                'expired_time' => $remote['ExpiredTime'] ?? null,
            ],
        ];
    }

    public function listInventory(): array
    {
        $out = [];
        $regions = \App\Models\DatacenterRegion::where('provider', 'tencent')->get();

        foreach ($regions as $region) {
            $pools = \App\Models\UpstreamQuotaPool::where('region_code', $region->code)->get();
            $out[] = [
                'code' => $region->code,
                'name' => $region->name,
                'country' => $region->country,
                'capacity' => $pools->sum('capacity') ?: null,
                'capacity_used' => $pools->sum('reserved') + $pools->sum('used'),
            ];
        }

        return $out;
    }

    // =====================================================================
    // Internals
    // =====================================================================

    /** Deterministic upstream name — the orphan-adoption key (layer 3). */
    public function upstreamName(VpsInstance $instance): string
    {
        return 'crava-'.$instance->order_id.'-'.$instance->id;
    }

    private function createOrAdopt(VpsInstance $instance, string $region, string $bundleId, string $blueprintId): string
    {
        $name = $this->upstreamName($instance);

        // Layer 3: adopt an orphan from a previous ambiguous attempt BEFORE
        // ever calling CreateInstances again.
        $found = $this->lighthouse->findInstanceByName($name, $region);
        if ($found && ! empty($found['InstanceId'])) {
            Log::info('provisioning.orphan_adopted', [
                'instance_id' => $instance->id,
                'upstream_id' => $found['InstanceId'],
            ]);

            return (string) $found['InstanceId'];
        }

        $resp = $this->lighthouse->createInstances([
            'BundleId' => $bundleId,
            'BlueprintId' => $blueprintId,
            'InstanceName' => $name,
            'InstanceCount' => 1,
            'InstanceChargePrepaid' => [
                'Period' => 1,
                'RenewFlag' => 'NOTIFY_AND_MANUAL_RENEW',
            ],
        ], $region);

        $instanceId = $resp['InstanceIdSet'][0] ?? null;
        if (! $instanceId) {
            // Ambiguous outcome — retryable; next attempt adopts by name.
            throw new ProviderException('CreateInstances returned no instance id.', true, 'MissingInstanceId');
        }

        return (string) $instanceId;
    }

    private function reached(?string $checkpoint, string $step, array $sequence = self::CHECKPOINTS): bool
    {
        if ($checkpoint === null) {
            return false;
        }

        $current = array_search($checkpoint, $sequence, true);
        $target = array_search($step, $sequence, true);

        if ($current === false || $target === false) {
            return false; // unknown checkpoint → re-run (steps are idempotent)
        }

        return $target <= $current;
    }

    private function checkpoint(ProvisioningTask $task, array &$output, string $checkpoint, int $progress, string $step): void
    {
        $output['checkpoint'] = $checkpoint;
        $task->update([
            'output' => $output,
            'progress' => $progress,
            'current_step' => $step, // neutral customer-facing wording
        ]);
    }

    /**
     * Bounded polling inside ONE job attempt. Exceeding the budget throws a
     * RETRYABLE exception — the queue job retries later and resumes from the
     * persistent checkpoint (no long sleeps blocking the worker).
     */
    private function poll(callable $check, ProvisioningTask $task, string $step): mixed
    {
        $interval = max(0, (int) config('services.tencent.poll_interval_seconds', 5));
        $maxWait = max(1, (int) config('services.tencent.poll_max_seconds', 240));
        $waited = 0;

        while (true) {
            $result = $check();
            if ($result !== null && $result !== false) {
                return $result;
            }

            if ($waited >= $maxWait) {
                throw new ProviderException("Timeout waiting for upstream condition ({$step}).", true, 'PollTimeout');
            }

            if ($interval > 0) {
                sleep($interval);
            }
            $waited += max(1, $interval);
            $task->markProgress($task->progress, $step);
        }
    }

    private function waitInstance(string $instanceId, string $region, ProvisioningTask $task, callable $predicate, string $step): array
    {
        return $this->poll(function () use ($instanceId, $region, $predicate) {
            $remote = $this->describe($instanceId, $region);

            return $predicate($remote) ? $remote : null;
        }, $task, $step);
    }

    private function waitAgentOnline(string $instanceId, string $region, ProvisioningTask $task): void
    {
        $this->poll(function () use ($instanceId, $region) {
            $resp = $this->tat->describeAutomationAgentStatus([$instanceId], $region);
            $status = strtoupper((string) ($resp['AutomationAgentStatusSet'][0]['AgentStatus'] ?? ''));

            return $status === 'ONLINE' ? true : null;
        }, $task, 'Menunggu server siap dikonfigurasi');
    }

    private function describe(string $instanceId, string $region): array
    {
        $resp = $this->lighthouse->describeInstances($region, [$instanceId]);

        return $resp['InstanceSet'][0] ?? [];
    }

    private function fillAddresses(VpsInstance $instance, array $remote): void
    {
        $public = $remote['PublicAddresses'][0] ?? null;
        $private = $remote['PrivateAddresses'][0] ?? null;

        if ($public && $public !== $instance->public_ip) {
            $instance->public_ip = $public;
        }
        if ($private && $private !== $instance->private_ip) {
            $instance->private_ip = $private;
        }
        $instance->save();
    }

    /**
     * Firewall diff (revision 5): open only ports not already covered.
     * Existing comma-separated port ranges are expanded so a default rule
     * like "80,443" counts as covering both ports.
     */
    private function openPorts(string $instanceId, array $ports, string $region, string $stackKey): void
    {
        $resp = $this->lighthouse->describeFirewallRules($instanceId, $region);
        $existing = $resp['FirewallRuleSet'] ?? [];

        $wanted = array_map(fn (int $port) => [
            'Protocol' => 'TCP',
            'Port' => (string) $port,
            'CidrBlock' => '0.0.0.0/0',
            'Action' => 'ACCEPT',
            'FirewallRuleDescription' => 'crava-'.$stackKey,
        ], $ports);

        $missing = $this->diffRules($existing, $wanted);

        if ($missing !== []) {
            $this->lighthouse->createFirewallRules($instanceId, $missing, $region);
        }
    }

    private function diffRules(array $existing, array $wanted): array
    {
        $covered = [];
        foreach ($existing as $rule) {
            $action = strtoupper((string) ($rule['Action'] ?? 'ACCEPT'));
            if ($action !== 'ACCEPT') {
                continue;
            }
            $protocol = strtoupper((string) ($rule['Protocol'] ?? 'TCP'));
            $cidr = (string) ($rule['CidrBlock'] ?? '0.0.0.0/0');
            foreach (explode(',', (string) ($rule['Port'] ?? '')) as $port) {
                $port = trim($port);
                if ($port !== '') {
                    $covered["{$protocol}|{$port}|{$cidr}"] = true;
                }
            }
        }

        $missing = [];
        foreach ($wanted as $rule) {
            $key = strtoupper($rule['Protocol'] ?? 'TCP').'|'.($rule['Port'] ?? '').'|'.($rule['CidrBlock'] ?? '0.0.0.0/0');
            if (! isset($covered[$key])) {
                $missing[] = $rule;
                $covered[$key] = true; // never request the same port twice
            }
        }

        return $missing;
    }

    /** OS family → default login user (config services.tencent.default_users). */
    public function defaultUserFor(string $os): string
    {
        $family = strtolower(preg_replace('/[^a-z].*$/i', '', $os) ?: $os);
        $users = (array) config('services.tencent.default_users', []);

        return (string) ($users[$family] ?? 'root');
    }

    /** Strong password meeting Lighthouse complexity rules (12–30 chars, 4 categories). */
    private function generatePassword(): string
    {
        return \App\Services\Provisioning\Support\ServerPassword::generate();
    }

    private function mapState(string $state): string
    {
        return match (strtoupper($state)) {
            'RUNNING' => 'running',
            'STOPPED', 'STOPPING' => 'stopped',
            'REBOOTING' => 'rebooting',
            'SHUTDOWN', 'ISOLATING' => 'suspended',
            'TERMINATED' => 'terminated',
            default => 'provisioning', // PENDING / STARTING / RECOVERING / unknown
        };
    }
}