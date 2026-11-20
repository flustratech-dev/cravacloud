<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Models\SupportTicket;
use App\Models\VpsInstance;
use App\Services\Provisioning\Contracts\SupportsStackInstall;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\ProvisioningFinalizer;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

/**
 * Stage 2 of the two-stage provisioning flow (D11): STACK INSTALL polling.
 *
 * Lightweight by design — each attempt performs ONE DescribeInvocationTasks
 * poll (no long sleeps inside the worker). While the invocation is still
 * RUNNING we throw a RETRYABLE ProviderException and let the queue backoff
 * provide the waiting; tries/timeout are raised accordingly.
 *
 *   SUCCESS → finalize (SupplierPurchase, quota commit, running/active, notify)
 *   FAILED/TIMEOUT → permanent failure (finalizer->fail + quota settlement)
 *   missing invocation id (edge resume) → invoke once via installStack(),
 *   persist it, then poll on the next attempt.
 */
class InstallStackJob extends AbstractProvisioningJob
{
    /** Many short polls instead of one long-blocking attempt. */
    public int $tries = 60;
    public int $timeout = 120;

    public function backoff(): array
    {
        return [10, 20, 30, 60, 60, 120];
    }

    protected function kind(): string
    {
        return 'install_stack';
    }

    protected function run($provider, ProvisioningTask $task): array
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            throw new \RuntimeException('VpsInstance not found for task '.$task->id);
        }

        if (! $provider instanceof SupportsStackInstall) {
            throw new ProviderException('Provider does not support stack install.', false, 'UnsupportedProvider');
        }

        if ($task->status !== 'running') {
            $task->markStarted('Memasang stack aplikasi');
        } else {
            $task->markProgress(70, 'Memasang stack aplikasi');
        }

        $output = $task->output ?? [];

        // Edge resume: invocation not recorded yet → invoke once, then poll.
        if (empty($output['invocation_id'])) {
            $invoked = $provider->installStack($instance, $task);
            $output['invocation_id'] = $invoked['invocation_id'];
            $task->update(['output' => $output]);

            throw new ProviderException('Stack install invoked; polling on next attempt.', true, 'InstallPending');
        }

        $result = $provider->stackInstallResult($instance, $task);

        if ($result['status'] === 'success') {
            return [
                'stack_install' => 'success',
                'invocation_id' => $output['invocation_id'],
                'install_output' => $result['output'] ?? null,
                'resource_id' => $instance->provider_resource_id,
                'public_ip' => $instance->public_ip,
                'private_ip' => $instance->private_ip,
                'ssh_port' => $instance->ssh_port,
                'ssh_username' => $instance->ssh_username,
                // No root_password here — credentials stay encrypted on the
                // instance; task.output is plaintext JSON (review patch A1).
                'meta' => $instance->provider_meta ?? [],
            ];
        }

        if ($result['status'] === 'failed') {
            throw new ProviderException(
                'Stack install failed upstream: '.mb_substr((string) ($result['output'] ?? ''), 0, 500),
                false,
                'StackInstallFailed',
            );
        }

        // Still running → queue backoff provides the wait (D11).
        throw new ProviderException('Stack install still running upstream.', true, 'InstallPending');
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            return;
        }

        // Reinstall context: the infra already exists — do NOT re-finalize as
        // a new provision (no SupplierPurchase / quota commit / provisioned
        // notification). Just complete the reinstall.
        if (($task->input['context'] ?? null) === 'reinstall') {
            $this->completeReinstall($task, $instance);
            return;
        }

        app(ProvisioningFinalizer::class)->finalize($task, $instance, $output);
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        // Reinstall failure must not flip the ORDER to failed or settle quota
        // (the server was already provisioned). Mark the instance error and
        // leave the audit ticket open for the team.
        if (($task->input['context'] ?? null) === 'reinstall') {
            $instance = $task->vpsInstance;
            if ($instance) {
                try {
                    app(VpsStateMachine::class)->transition($instance, 'error', [
                        'reason' => 'Instalasi stack reinstall gagal: '.$error,
                        'actor_type' => 'system',
                    ]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            return;
        }

        app(ProvisioningFinalizer::class)->fail($task, $error);
    }

    /** Finish a reinstall once its stack install succeeded (Phase 5B). */
    private function completeReinstall(ProvisioningTask $task, VpsInstance $instance): void
    {
        // Refresh the panel first-run URL for the (possibly changed) stack.
        $stackKey = (string) ($task->input['stack'] ?? $instance->control_panel ?: 'none');
        $definition = app(StackRegistry::class)->get($stackKey);
        if ($instance->public_ip) {
            $instance->app_url = $definition?->appUrlTemplate
                ? str_replace('{ip}', (string) $instance->public_ip, $definition->appUrlTemplate)
                : null;
            $instance->save();
        }

        try {
            app(VpsStateMachine::class)->transition($instance, 'running', [
                'reason' => 'Reinstall + instalasi stack selesai.',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id],
                'allowSame' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.reinstall_install_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }

        try {
            $instance->logActivity('reinstall', "VPS {$instance->hostname} selesai dipasang ulang.", 'completed');
        } catch (\Throwable $e) {
            // ignore
        }

        $ticketId = $task->input['ticket_id'] ?? null;
        if ($ticketId) {
            $ticket = SupportTicket::find($ticketId);
            if ($ticket && $ticket->isOpen()) {
                $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
            }
        }
    }
}