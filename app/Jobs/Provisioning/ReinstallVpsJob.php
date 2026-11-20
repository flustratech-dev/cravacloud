<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Models\SupportTicket;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

/**
 * Day-2 reinstall (Phase 5B). provider->reinstall() performs the full flow:
 * ResetInstance → RUNNING → reset the new OS default user's password →
 * firewall diff → (stack) TAT RunCommand invoke.
 *
 * On success:
 *   - no stack → VPS reinstalling → running, resolve the audit ticket;
 *   - stack pending → hand off stage 2 (lightweight TAT polling) to
 *     InstallStackJob in 'reinstall' context; the instance stays
 *     'reinstalling' until the install completes.
 *
 * Credentials stay encrypted on the instance — NEVER copied into task.output.
 */
class ReinstallVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string { return 'reinstall'; }

    protected function run($provider, ProvisioningTask $task): array
    {
        $task->markProgress(15, 'Menyiapkan OS baru');
        $result = $provider->reinstall($task->vpsInstance, $task);

        // When stage 2 (install) takes over at 85%, skip the 90% mark so
        // progress never regresses.
        if (empty($result['stack_install_pending'])) {
            $task->markProgress(90, 'Instalasi hampir selesai');
        }

        return $result;
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (!$instance) return;

        $instance->fill([
            'os' => $output['os'] ?? $instance->os,
            'control_panel' => $output['control_panel'] ?? $instance->control_panel,
            'ssh_username' => $output['ssh_username'] ?? $instance->ssh_username,
        ])->save();

        if (!empty($output['stack_install_pending'])) {
            // Stage 2: TAT polling in reinstall context (no re-provisioning).
            $installTask = ProvisioningTask::create([
                'order_id' => $task->order_id,
                'vps_instance_id' => $instance->id,
                'kind' => 'install_stack',
                'status' => 'pending',
                'provider' => $task->provider,
                'progress' => 85,
                'current_step' => 'Memasang stack aplikasi',
                'input' => [
                    'context' => 'reinstall',
                    'stack' => $output['control_panel'] ?? ($instance->control_panel ?: 'none'),
                    'invocation_id' => $output['invocation_id'] ?? null,
                    'ticket_id' => $task->input['ticket_id'] ?? null,
                ],
                'output' => array_filter([
                    'invocation_id' => $output['invocation_id'] ?? null,
                ]),
            ]);

            $this->transition($instance, 'reinstalling', 'Reinstall: instalasi stack berjalan.', true);
            InstallStackJob::dispatch($installTask->id);
            return;
        }

        $this->transition($instance, 'reinstalling', 'Reinstall dimulai.', true);
        $this->transition($instance->refresh(), 'running', 'Reinstall selesai.');
        $this->resolveTicket($task);
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

    private function transition($instance, string $to, string $reason, bool $allowSame = false): void
    {
        try {
            app(VpsStateMachine::class)->transition($instance, $to, [
                'reason' => $reason,
                'actor_type' => 'system',
                'allowSame' => $allowSame,
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.reinstall_transition_failed', ['to' => $to, 'error' => $e->getMessage()]);
        }
    }

    /** Close the audit ticket once the automatic reinstall finished. */
    private function resolveTicket(ProvisioningTask $task): void
    {
        $ticketId = $task->input['ticket_id'] ?? null;
        if (!$ticketId) return;

        $ticket = SupportTicket::find($ticketId);
        if ($ticket && $ticket->isOpen()) {
            $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        }
    }
}