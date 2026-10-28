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
        try {
            app(VpsStateMachine::class)->transition($task->vpsInstance, 'stopped', [
                'reason' => 'Stop job selesai.',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.stop_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        // Stop failure biasanya harmless — biarkan status seperti sebelumnya.
    }
}
