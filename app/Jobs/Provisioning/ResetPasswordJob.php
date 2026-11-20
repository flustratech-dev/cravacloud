<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Notifications\VpsPasswordResetNotification;
use App\Services\Provisioning\Contracts\SupportsPasswordReset;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\Support\ServerPassword;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5B — self-service / admin server password reset (kind=reset_password).
 *
 * A strong password is generated INSIDE the job and handed straight to
 * provider->resetPassword(), which persists it encrypted on the instance
 * (initial_root_password cast) and polls until the reset settles. The secret
 * NEVER touches task.input or task.output (both plaintext JSON).
 *
 * On success: VpsActivityLog + customer email/WA notification (the message
 * points to the dashboard reveal — it does not contain the password).
 */
class ResetPasswordJob extends AbstractProvisioningJob
{
    protected function kind(): string
    {
        return 'reset_password';
    }

    protected function run($provider, ProvisioningTask $task): array
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            throw new \RuntimeException('VpsInstance not found for task '.$task->id);
        }

        if (! $provider instanceof SupportsPasswordReset) {
            throw new ProviderException('Provider does not support password reset.', false, 'UnsupportedProvider');
        }

        $task->markProgress(20, 'Mereset password server');

        // Generated here, passed directly to the provider — never persisted
        // in task.input/output. The provider stores it encrypted.
        $password = ServerPassword::generate();
        $result = $provider->resetPassword($instance, $task, $password);

        return [
            'status' => $result['status'] ?? 'running',
            'ssh_username' => $result['ssh_username'] ?? $instance->ssh_username,
        ];
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            return;
        }

        try {
            app(VpsStateMachine::class)->transition($instance, 'running', [
                'reason' => 'Reset password selesai.',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id],
                'allowSame' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.reset_password_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }

        try {
            $instance->logActivity('reset_password', "Password server {$instance->hostname} berhasil diperbarui.", 'completed');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $instance->customer?->notify(new VpsPasswordResetNotification($instance->fresh()));
        } catch (\Throwable $e) {
            Log::warning('provisioning.reset_password_notify_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            return;
        }

        // The server keeps running with its previous credential if the reset
        // could not be applied — do NOT flip it to error. Record the failure.
        try {
            $instance->logActivity('reset_password', 'Reset password server gagal: '.$error, 'failed');
        } catch (\Throwable $e) {
            // ignore
        }

        Log::warning('provisioning.reset_password_failed', ['task_id' => $task->id, 'error' => $error]);
    }
}