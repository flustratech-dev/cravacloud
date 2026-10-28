<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

class RebootVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string { return 'reboot'; }

    public function __construct(int $taskId, public bool $force = false)
    {
        parent::__construct($taskId);
    }

    protected function run($provider, ProvisioningTask $task): array
    {
        $task->markProgress(20, $this->force ? 'Hard reset' : 'Soft reboot');
        return $this->force
            ? $provider->forceReboot($task->vpsInstance, $task)
            : $provider->reboot($task->vpsInstance, $task);
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        if (!$task->vpsInstance) return;
        try {
            $sm = app(VpsStateMachine::class);
            $sm->transition($task->vpsInstance, 'rebooting', [
                'reason' => 'Reboot dimulai.',
                'actor_type' => 'system',
                'allowSame' => true,
            ]);
            $sm->transition($task->vpsInstance->refresh(), 'running', [
                'reason' => 'Reboot selesai.',
                'actor_type' => 'system',
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.reboot_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        if (!$task->vpsInstance) return;
        try {
            app(VpsStateMachine::class)->transition($task->vpsInstance, 'error', [
                'reason' => 'Reboot gagal: ' . $error,
                'actor_type' => 'system',
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
