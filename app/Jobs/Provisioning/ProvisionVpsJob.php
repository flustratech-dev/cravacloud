<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Services\Provisioning\ProvisioningFinalizer;

/**
 * Stage 1 of the two-stage provisioning flow (D11): INFRA only.
 *   Order paid → provisioning (orchestrator)
 *   provider->provision(): create/adopt → RUNNING → password → firewall → TAT invoke
 *
 * On success:
 *   - stack install pending → create an install_stack task + dispatch
 *     InstallStackJob (stage 2 does the lightweight TAT polling);
 *   - no stack → finalize immediately (ProvisioningFinalizer).
 *
 * Kalau semua attempt gagal → Order pindah ke `failed`, VPS ke `error`
 * (via ProvisioningFinalizer::fail, termasuk settlement kuota).
 */
class ProvisionVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string
    {
        return 'provision';
    }

    protected function run($provider, ProvisioningTask $task): array
    {
        $instance = $task->vpsInstance;
        if (!$instance) {
            throw new \RuntimeException('VpsInstance not found for task ' . $task->id);
        }
        $task->markProgress(10, 'Memulai provisioning');
        $result = $provider->provision($instance, $task);

        // Review patch A3: skip the 80% mark when stage 2 (install) takes
        // over at 65% — otherwise progress would regress 65% → 80% → 65%.
        if (empty($result['stack_install_pending'])) {
            $task->markProgress(80, 'Menyimpan detail server');
        }

        return $result;
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (!$instance) return;

        $finalizer = app(ProvisioningFinalizer::class);

        // Persist infra details NOW so the dashboard shows IP/credentials
        // while the stack install (stage 2) is still running.
        $finalizer->applyInstanceDetails($instance, $output);

        if (!empty($output['stack_install_pending'])) {
            $installTask = ProvisioningTask::create([
                'order_id' => $task->order_id,
                'vps_instance_id' => $instance->id,
                'kind' => 'install_stack',
                'status' => 'pending',
                'provider' => $task->provider,
                'progress' => 65,
                'current_step' => 'Memasang stack aplikasi',
                'input' => [
                    'stack' => $output['meta']['stack'] ?? ($instance->control_panel ?: 'none'),
                    'invocation_id' => $output['invocation_id'] ?? null,
                ],
                'output' => array_filter([
                    'invocation_id' => $output['invocation_id'] ?? null,
                ]),
            ]);

            InstallStackJob::dispatch($installTask->id);
            return;
        }

        $finalizer->finalize($task, $instance, $output);
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        app(ProvisioningFinalizer::class)->fail($task, $error);
    }
}
