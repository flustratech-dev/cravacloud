<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\ProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Base class untuk semua provisioning job async.
 *
 * Kontrak:
 *   - Subclass define ->kind (provision/start/stop/reboot/reinstall)
 *   - Subclass implement ->run($provider, $task) : array (output)
 *   - Subclass implement ->onSuccess($output) : void (update instance state)
 *   - Subclass implement ->onFailure(string $error) : void (update instance state)
 *
 * Retry policy: 5 attempts, backoff exponential [10, 30, 60, 120, 300] detik.
 * Failure di attempt terakhir → mark task failed + trigger onFailure.
 */
abstract class AbstractProvisioningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 600; // 10 menit per attempt

    public function __construct(public int $taskId)
    {
    }

    /** @return int[] Backoff detik per attempt. */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    abstract protected function kind(): string;

    /** Execute provider call. Boleh throw ProviderException. */
    abstract protected function run($provider, ProvisioningTask $task): array;

    /** Panggil setelah task sukses (update instance state via VpsStateMachine). */
    abstract protected function onSuccess(ProvisioningTask $task, array $output): void;

    /** Panggil kalau task gagal permanen (semua attempt habis). */
    abstract protected function onFailure(ProvisioningTask $task, string $error): void;

    public function handle(ProviderManager $manager): void
    {
        /** @var ProvisioningTask|null $task */
        $task = ProvisioningTask::find($this->taskId);
        if (!$task) {
            Log::warning('provisioning.task_missing', ['task_id' => $this->taskId]);
            return;
        }

        if (in_array($task->status, ['succeeded', 'cancelled'], true)) {
            return; // sudah beres.
        }

        try {
            $provider = $manager->provider($task->provider);
            $output = $this->run($provider, $task);
            $task->markSucceeded($output);
            $this->onSuccess($task->fresh(), $output);
        } catch (ProviderException $e) {
            Log::warning('provisioning.provider_error', [
                'task_id' => $task->id,
                'kind' => $task->kind,
                'attempt' => $this->attempts(),
                'retryable' => $e->retryable,
                'error' => $e->getMessage(),
            ]);
            if (!$e->retryable) {
                $task->markFailed($e->getMessage());
                $this->onFailure($task->fresh(), $e->getMessage());
                $this->fail($e);
                return;
            }
            throw $e; // retry
        } catch (\Throwable $e) {
            Log::error('provisioning.exception', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $task = ProvisioningTask::find($this->taskId);
        if (!$task) return;
        $task->markFailed($e->getMessage());
        $this->onFailure($task->fresh(), $e->getMessage());
    }
}
