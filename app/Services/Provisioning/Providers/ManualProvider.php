<?php

namespace App\Services\Provisioning\Providers;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Services\Provisioning\Contracts\VpsProviderContract;
use App\Services\Provisioning\Exceptions\ProviderException;

/**
 * ManualProvider — provider "no-op" untuk workflow existing dimana admin
 * memasukkan detail server secara manual (IP, password root).
 *
 * Semua operasi jadi placeholder yang menyalin dari task input ke instance
 * meta, tanpa memanggil API eksternal. Ini yang jadi behavior default.
 *
 * Untuk provider real (Supplier, BiznetGio, Contabo), buat kelas baru yang
 * implement contract dan register di config/services.php.
 */
class ManualProvider implements VpsProviderContract
{
    public function name(): string
    {
        return 'manual';
    }

    public function provision(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Menerima input manual admin');
        $input = $task->input ?? [];

        // Admin sudah setup server offline. Data langsung dari input.
        $task->markProgress(50, 'Menyimpan detail server');

        $result = [
            'resource_id' => $input['resource_id'] ?? 'manual-' . $instance->id,
            'public_ip' => $input['public_ip'] ?? $instance->public_ip,
            'private_ip' => $input['private_ip'] ?? $instance->private_ip,
            'ssh_port' => $input['ssh_port'] ?? 22,
            'root_password' => $input['root_password'] ?? null,
            'meta' => ['provider' => 'manual', 'provisioned_by' => $task->actor_user_id],
        ];

        $task->markProgress(90, 'Server siap');
        return $result;
    }

    public function start(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Manual start (no-op)');
        return ['status' => 'running'];
    }

    public function stop(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Manual stop (no-op)');
        return ['status' => 'stopped'];
    }

    public function reboot(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Manual reboot (no-op)');
        return ['status' => 'running'];
    }

    public function forceReboot(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Manual force reboot (no-op)');
        return ['status' => 'running'];
    }

    public function reinstall(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Manual reinstall (admin memproses offline)');
        $input = $task->input ?? [];
        return [
            'status' => 'running',
            'os' => $input['os'] ?? $instance->os,
            'control_panel' => $input['control_panel'] ?? $instance->control_panel,
        ];
    }

    public function destroy(VpsInstance $instance, ProvisioningTask $task): array
    {
        $task->markStarted('Manual destroy');
        return ['status' => 'destroyed'];
    }

    public function status(VpsInstance $instance): array
    {
        // Manual tidak bisa query provider — kembalikan status yang sudah di DB.
        return [
            'status' => $instance->status,
            'meta' => ['source' => 'manual', 'note' => 'reconcile no-op'],
        ];
    }

    public function listInventory(): array
    {
        return [
            ['code' => 'ID-CGK01', 'name' => 'Jakarta, Indonesia', 'country' => 'ID', 'capacity' => null, 'capacity_used' => 0],
            ['code' => 'SG-SIN01', 'name' => 'Singapore', 'country' => 'SG', 'capacity' => null, 'capacity_used' => 0],
        ];
    }
}
