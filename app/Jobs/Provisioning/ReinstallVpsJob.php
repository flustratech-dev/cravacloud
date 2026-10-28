<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

class ReinstallVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string { return 'reinstall'; }

    protected function run($provider, ProvisioningTask $task): array
    {
        $task->markProgress(15, 'Menyiapkan OS baru');
        $result = $provider->reinstall($task->vpsInstance, $task);
        $task->markProgress(90, 'Instalasi hampir selesai');
        return $result;
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (!$instance) return;

        $instance->fill([
            'os' => $output['os'] ?? $instance->os,
            'control_panel' => $output['control_panel'] ?? $instance->control_panel,
        ])->save();

        try {
            $sm = app(VpsStateMachine::class);
            $sm->transition($instance, 'reinstalling', [
                'reason' => 'Reinstall dimulai.',
                'actor_type' => 'system',
                'allowSame' => true,
            ]);
            $sm->transition($instance->refresh(), 'running', [
                'reason' => 'Reinstall selesai.',
                'actor_type' => 'system',
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.reinstall_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        if (!$task->vpsInstance) return;
        try {
            app(VpsStateMachine::class)->transition($task->vpsInstance, 'error', [
                'reason' => 'Reinstall gagal: ' . $error,
                'actor_type' => 'system',
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
