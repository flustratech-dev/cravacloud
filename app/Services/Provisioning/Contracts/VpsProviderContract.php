<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\ProvisioningTask;
use App\Models\VpsInstance;

/**
 * Kontrak provider VPS. Setiap provider (manual, supplier, biznetgio, dst.)
 * meng-implement interface ini agar bisa di-swap tanpa mengubah code caller.
 *
 * SEMUA method boleh throw ProviderException untuk failure yang dapat di-retry.
 * Method boleh update $task (progress, current_step) selama berjalan.
 */
interface VpsProviderContract
{
    /** Nama unique provider (harus match key di config/services). */
    public function name(): string;

    /**
     * Buat resource VPS baru. Return array minimal:
     *   ['resource_id' => string, 'public_ip' => string, 'private_ip' => ?string,
     *    'ssh_port' => int, 'root_password' => string, 'meta' => array]
     */
    public function provision(VpsInstance $instance, ProvisioningTask $task): array;

    /** Power on. Return status baru dari provider. */
    public function start(VpsInstance $instance, ProvisioningTask $task): array;

    /** Power off (graceful). */
    public function stop(VpsInstance $instance, ProvisioningTask $task): array;

    /** Soft reboot. */
    public function reboot(VpsInstance $instance, ProvisioningTask $task): array;

    /** Hard reset. */
    public function forceReboot(VpsInstance $instance, ProvisioningTask $task): array;

    /** Reinstall OS. Input di $task->input. */
    public function reinstall(VpsInstance $instance, ProvisioningTask $task): array;

    /** Destroy permanent (setelah terminate). */
    public function destroy(VpsInstance $instance, ProvisioningTask $task): array;

    /**
     * Query status real dari provider (untuk reconcile).
     * Return ['status' => 'running|stopped|...' , 'meta' => array].
     */
    public function status(VpsInstance $instance): array;

    /**
     * List datacenter/region + capacity yang provider ini support.
     * Return array of ['code','name','country','capacity','capacity_used'].
     */
    public function listInventory(): array;
}
