<?php

namespace App\Services\Provisioning;

use App\Jobs\Provisioning\RebootVpsJob;
use App\Jobs\Provisioning\ResetPasswordJob;
use App\Jobs\Provisioning\StartVpsJob;
use App\Jobs\Provisioning\StopVpsJob;
use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\Provisioning\Contracts\SupportsFirewall;
use App\Services\Provisioning\Contracts\SupportsPasswordReset;
use App\Services\Provisioning\Contracts\SupportsTrafficQuota;
use App\Services\Provisioning\Contracts\SupportsUpgrade;
use App\Services\Provisioning\Contracts\SupportsVncConsole;
use App\Services\Provisioning\Exceptions\Day2Exception;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\UpgradeService;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5D — shared Day-2 self-service engine for the customer AND admin
 * VpsActionControllers. All upstream capability checks, state guards, duplicate
 * guards and white-label messaging live here so the two controllers stay thin
 * and behave identically. Manual (non-tencent) instances are never capable —
 * the UI keeps the legacy ticket/manual buttons for them.
 *
 * STRICT white-label: no vendor name, bundle id or upstream detail ever leaves
 * this service in a customer-facing message. The VNC URL is returned on-demand
 * and NEVER logged, cached or persisted.
 */
class VpsDay2Service
{
    public function __construct(
        private readonly ProviderManager $manager,
        private readonly VpsLifecycleDispatcher $lifecycle,
        private readonly UpgradeService $upgrades,
    ) {
    }

    /** Only auto-provisioned tencent instances with an upstream resource id. */
    public function capable(VpsInstance $vps): bool
    {
        return $this->lifecycle->supports($vps);
    }

    /** Capability map used by the UI to gate the Day-2 panel. */
    public function capabilities(VpsInstance $vps): array
    {
        $none = [
            'capable' => false, 'power' => false, 'console' => false,
            'reset_password' => false, 'firewall' => false, 'traffic' => false, 'upgrade' => false,
        ];

        if (! $this->capable($vps)) {
            return $none;
        }

        $provider = $this->manager->provider($vps->provider);

        return [
            'capable' => true,
            'power' => true,
            'console' => $provider instanceof SupportsVncConsole,
            'reset_password' => $provider instanceof SupportsPasswordReset,
            'firewall' => $provider instanceof SupportsFirewall,
            'traffic' => $provider instanceof SupportsTrafficQuota,
            'upgrade' => $provider instanceof SupportsUpgrade,
        ];
    }

    // =====================================================================
    // Power
    // =====================================================================

    public function power(VpsInstance $vps, string $action): array
    {
        $this->provider($vps);
        $action = strtolower(trim($action));

        if (! in_array($action, ['start', 'stop', 'reboot', 'force_reboot'], true)) {
            throw new Day2Exception('Aksi power tidak dikenal.', 'invalid_action');
        }

        $status = $vps->status;
        if ($action === 'start' && ! in_array($status, ['stopped', 'suspended', 'error'], true)) {
            throw new Day2Exception('Server hanya bisa dinyalakan saat berhenti atau ditangguhkan.', 'invalid_state');
        }
        if (in_array($action, ['stop', 'reboot', 'force_reboot'], true) && $status !== 'running') {
            throw new Day2Exception('Aksi ini hanya bisa dilakukan saat server aktif (running).', 'invalid_state');
        }

        $kind = $action === 'force_reboot' ? 'reboot' : $action;
        if ($this->hasLiveTask($vps, $kind)) {
            throw new Day2Exception('Aksi sedang diproses. Tunggu selesai sebelum mencoba lagi.', 'busy');
        }

        $force = $action === 'force_reboot';
        $task = ProvisioningTask::create([
            'order_id' => $vps->order_id,
            'vps_instance_id' => $vps->id,
            'kind' => $kind,
            'status' => 'pending',
            'provider' => $vps->provider,
            'input' => array_filter([
                'action' => $action,
                'force' => $force,
                'target_status' => $kind === 'stop' ? 'stopped' : null,
            ]),
        ]);

        if ($kind === 'reboot') {
            $this->transition($vps, 'rebooting', 'Reboot diminta.', true);
        }

        match ($kind) {
            'start' => StartVpsJob::dispatch($task->id),
            'stop' => StopVpsJob::dispatch($task->id),
            'reboot' => RebootVpsJob::dispatch($task->id, $force),
        };

        $this->log($vps, 'power', 'Aksi power diminta: '.$action, 'pending');

        return ['task_id' => $task->id, 'action' => $action, 'status' => 'processing'];
    }

    // =====================================================================
    // Reset password
    // =====================================================================

    public function resetPassword(VpsInstance $vps): array
    {
        $provider = $this->provider($vps);
        if (! $provider instanceof SupportsPasswordReset) {
            throw new Day2Exception('Reset password mandiri tidak tersedia untuk server ini.', 'unsupported');
        }
        if ($vps->status !== 'running') {
            throw new Day2Exception('Reset password hanya bisa dilakukan saat server aktif (running).', 'invalid_state');
        }
        if ($this->hasLiveTask($vps, 'reset_password')) {
            throw new Day2Exception('Reset password sedang diproses.', 'busy');
        }

        $task = ProvisioningTask::create([
            'order_id' => $vps->order_id,
            'vps_instance_id' => $vps->id,
            'kind' => 'reset_password',
            'status' => 'pending',
            'provider' => $vps->provider,
            'input' => [],
        ]);

        ResetPasswordJob::dispatch($task->id);
        $this->log($vps, 'reset_password', 'Reset password diminta.', 'pending');

        return ['task_id' => $task->id, 'status' => 'processing'];
    }

    // =====================================================================
    // VNC console (on-demand, single-use, NEVER logged/cached)
    // =====================================================================

    public function console(VpsInstance $vps): array
    {
        $provider = $this->provider($vps);
        if (! $provider instanceof SupportsVncConsole) {
            throw new Day2Exception('Konsol server tidak tersedia untuk server ini.', 'unsupported');
        }
        if ($vps->status !== 'running') {
            throw new Day2Exception('Konsol hanya bisa dibuka saat server aktif (running).', 'not_running');
        }

        try {
            // Exactly ONE upstream call per request; the URL is single-use.
            return $provider->vncUrl($vps);
        } catch (ProviderException $e) {
            $code = $e->providerCode === 'InstanceNotRunning' ? 'not_running' : 'console_error';
            throw new Day2Exception(
                $code === 'not_running'
                    ? 'Konsol hanya bisa dibuka saat server aktif (running).'
                    : 'Konsol sedang tidak tersedia. Coba lagi sesaat.',
                $code
            );
        }
    }

    // =====================================================================
    // Firewall
    // =====================================================================

    public function firewallRules(VpsInstance $vps): array
    {
        $provider = $this->provider($vps);
        if (! $provider instanceof SupportsFirewall) {
            throw new Day2Exception('Manajemen firewall tidak tersedia untuk server ini.', 'unsupported');
        }

        return array_map(fn (array $r) => [
            'protocol' => strtoupper((string) ($r['Protocol'] ?? 'TCP')),
            'port' => (string) ($r['Port'] ?? ''),
            'cidr' => (string) ($r['CidrBlock'] ?? '0.0.0.0/0'),
            'action' => strtoupper((string) ($r['Action'] ?? 'ACCEPT')),
            'description' => (string) ($r['FirewallRuleDescription'] ?? ''),
        ], $provider->listFirewallRules($vps));
    }

    public function addFirewallRule(VpsInstance $vps, array $input): array
    {
        $provider = $this->provider($vps);
        if (! $provider instanceof SupportsFirewall) {
            throw new Day2Exception('Manajemen firewall tidak tersedia untuk server ini.', 'unsupported');
        }

        $rule = [
            'Protocol' => strtoupper((string) $input['protocol']),
            'Port' => (string) $input['port'],
            'CidrBlock' => (string) ($input['cidr'] ?? '0.0.0.0/0'),
            'Action' => strtoupper((string) ($input['action'] ?? 'ACCEPT')),
            'FirewallRuleDescription' => mb_substr((string) ($input['description'] ?? 'crava-custom'), 0, 100),
        ];

        $result = $provider->addFirewallRules($vps, [$rule]);
        $this->log($vps, 'firewall', 'Menambah aturan firewall: '.$rule['Protocol'].' '.$rule['Port'], 'completed');

        return $result;
    }

    public function deleteFirewallRule(VpsInstance $vps, array $input, bool $confirmSsh = false): array
    {
        $provider = $this->provider($vps);
        if (! $provider instanceof SupportsFirewall) {
            throw new Day2Exception('Manajemen firewall tidak tersedia untuk server ini.', 'unsupported');
        }

        $protocol = strtoupper((string) $input['protocol']);
        $port = (string) $input['port'];
        $cidr = (string) ($input['cidr'] ?? '0.0.0.0/0');

        // Protect SSH: deleting a rule that covers port 22 needs explicit
        // confirmation so the customer cannot accidentally lock themselves out.
        if ($this->coversSsh($protocol, $port) && ! $confirmSsh) {
            throw new Day2Exception(
                'Menghapus aturan SSH (port 22) dapat mengunci akses ke server Anda. Konfirmasi diperlukan.',
                'ssh_protection'
            );
        }

        $rule = [
            'Protocol' => $protocol,
            'Port' => $port,
            'CidrBlock' => $cidr,
            'Action' => strtoupper((string) ($input['action'] ?? 'ACCEPT')),
        ];

        $result = $provider->deleteFirewallRules($vps, [$rule]);
        $this->log($vps, 'firewall', 'Menghapus aturan firewall: '.$protocol.' '.$port, 'completed');

        return $result;
    }

    // =====================================================================
    // Traffic quota
    // =====================================================================

    public function traffic(VpsInstance $vps): array
    {
        $provider = $this->provider($vps);
        if (! $provider instanceof SupportsTrafficQuota) {
            throw new Day2Exception('Informasi kuota trafik tidak tersedia untuk server ini.', 'unsupported');
        }

        return $provider->trafficUsage($vps);
    }

    // =====================================================================
    // Upgrade (prorata difference invoice; upstream applied after payment)
    // =====================================================================

    public function upgradePreview(VpsInstance $vps, VpsSpec $targetSpec): array
    {
        $this->provider($vps);

        return $this->upgrades->preview($vps, $targetSpec);
    }

    public function createUpgrade(VpsInstance $vps, VpsSpec $targetSpec): array
    {
        $this->provider($vps);
        $preview = $this->upgrades->preview($vps, $targetSpec);

        if (! $preview['ok']) {
            throw new Day2Exception(
                $preview['error'] ?? 'Upgrade tidak dapat diproses.',
                $preview['code'] ?? 'invalid',
                $preview['replacement_spec_id'] ?? null
            );
        }

        $result = $this->upgrades->createUpgradeOrder($vps, $targetSpec);
        $this->log($vps, 'upgrade', 'Order upgrade dibuat (menunggu pembayaran).', 'pending');

        return [
            'order_id' => $result['order']->id,
            'invoice_id' => $result['invoice']->id,
            'total' => $result['preview']['total'] ?? null,
            'currency' => $result['preview']['currency'] ?? 'IDR',
            'status' => 'awaiting_payment',
        ];
    }

    // =====================================================================
    // Internals
    // =====================================================================

    private function provider(VpsInstance $vps)
    {
        if (! $this->lifecycle->supports($vps)) {
            throw new Day2Exception(
                'Server ini belum mendukung aksi mandiri. Gunakan tombol bantuan atau hubungi support.',
                'unsupported'
            );
        }

        return $this->manager->provider($vps->provider);
    }

    private function hasLiveTask(VpsInstance $vps, string $kind): bool
    {
        return ProvisioningTask::where('vps_instance_id', $vps->id)
            ->where('kind', $kind)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }

    private function transition(VpsInstance $vps, string $to, string $reason, bool $allowSame = false): void
    {
        try {
            app(VpsStateMachine::class)->transition($vps, $to, [
                'reason' => $reason,
                'actor_type' => 'customer',
                'allowSame' => $allowSame,
            ]);
        } catch (\Throwable $e) {
            Log::warning('day2.transition_failed', ['instance_id' => $vps->id, 'to' => $to, 'error' => $e->getMessage()]);
        }
    }

    private function log(VpsInstance $vps, string $action, string $description, string $status): void
    {
        try {
            $vps->logActivity($action, $description, $status);
        } catch (\Throwable $e) {
            Log::warning('day2.log_activity_failed', ['instance_id' => $vps->id, 'error' => $e->getMessage()]);
        }
    }

    /** Does a protocol/port string cover SSH (TCP 22)? Handles comma lists + ranges. */
    private function coversSsh(string $protocol, string $port): bool
    {
        if (! in_array($protocol, ['TCP', 'ALL'], true)) {
            return false;
        }

        foreach (explode(',', $port) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            if (str_contains($segment, '-')) {
                [$lo, $hi] = array_pad(explode('-', $segment, 2), 2, null);
                if (is_numeric($lo) && is_numeric($hi) && (int) $lo <= 22 && (int) $hi >= 22) {
                    return true;
                }
            } elseif ((int) $segment === 22) {
                return true;
            }
        }

        return false;
    }
}