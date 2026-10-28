<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

class StartVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string { return 'start'; }

    protected function run($provider, ProvisioningTask $task): array
    {
        $task->markProgress(20, 'Power on');
        return $provider->start($task->vpsInstance, $task);
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        if (!$task->vpsInstance) return;
        try {
            app(VpsStateMachine::class)->transition($task->vpsInstance, 'running', [
                'reason' => 'Start job selesai.',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.start_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        if (!$task->vpsInstance) return;
        try {
            app(VpsStateMachine::class)->transition($task->vpsInstance, 'error', [
                'reason' => 'Start gagal: ' . $error,
                'actor_type' => 'system',
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
