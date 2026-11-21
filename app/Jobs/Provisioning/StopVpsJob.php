<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

class StopVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string { return 'stop'; }

    protected function run($provider, ProvisioningTask $task): array
    {
        $task->markProgress(20, 'Power off');
        return $provider->stop($task->vpsInstance, $task);
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        if (!$task->vpsInstance) return;

        // Phase 4C: the suspend flow (billing cron / admin) asks for
        // 'suspended' AFTER the upstream stop succeeded; default stays
        // 'stopped' so existing behavior is unchanged.
        $target = (string) ($task->input['target_status'] ?? 'stopped');
        if (!in_array($target, ['stopped', 'suspended'], true)) {
            $target = 'stopped';
        }

        try {
            app(VpsStateMachine::class)->transition($task->vpsInstance, $target, [
                'reason' => 'Stop job selesai.',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id],
                'allowSame' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.stop_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        // Stop failure biasanya harmless — biarkan status seperti sebelumnya.
        // Kecuali flow suspend (Phase 4C): VPS tidak jadi suspended, admin
        // harus tahu supaya bisa menindaklanjuti manual.
        if (($task->input['target_status'] ?? null) === 'suspended') {
            $instance = $task->vpsInstance;
            app(\App\Services\AdminAlertService::class)->alert(
                'provisioning.failed',
                [
                    'title' => 'Suspend upstream gagal',
                    'order_id' => $task->order_id,
                    'details' => [
                        'Task' => "#{$task->id} (suspend)",
                        'Instance' => '#' . ($instance?->id ?? '?') . ' (' . ($instance?->hostname ?? '-') . ')',
                        'Error' => \Illuminate\Support\Str::limit($error, 160),
                    ],
                    'body' => 'Instance #' . ($instance?->id ?? '?') . ' (' . ($instance?->hostname ?? '-') . ") gagal dimatikan di upstream (task #{$task->id}). "
                        . "Status VPS belum suspended; periksa manual.\n\nError: {$error}",
                    'action_url' => url('/admin/monitoring?tab=tasks&status=failed'),
                ],
                dedupeKey: 'task-' . $task->id,
                cooldownSeconds: 3600
            );
        }
    }
}
