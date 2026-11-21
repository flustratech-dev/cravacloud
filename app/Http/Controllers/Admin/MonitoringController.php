<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProvisioningTask;
use App\Models\SupportTicket;
use App\Models\UpstreamQuotaPool;
use App\Models\VpsInstance;
use App\Models\VpsStatusHistory;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\Tencent\BalanceGuard;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MonitoringController extends Controller
{
    use Concerns\LogsAdminAudit;

    public function __construct(
        protected OrderStateMachine $orderStateMachine
    ) {
    }

    public function monitoring(Request $request)
    {
        $tab = $request->query('tab', 'overview');
        if (! in_array($tab, ['overview', 'tasks', 'upstream', 'failed-jobs'], true)) {
            $tab = 'overview';
        }

        $statusCounts = VpsInstance::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $problemInstances = VpsInstance::with('customer')->whereIn('status', ['error', 'suspended', 'terminated'])->latest('updated_at')->take(25)->get();
        $staleProvisioning = VpsInstance::with('customer')->where('status', 'provisioning')->where('created_at', '<', now()->subMinutes(30))->latest()->get();
        $slaBreaches = SupportTicket::with(['customer', 'assignee'])->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->whereNull('first_response_at')->latest('sla_due_at')->get();
        $recentTransitions = VpsStatusHistory::with('vpsInstance')->latest('created_at')->take(25)->get();

        $quotaPools = UpstreamQuotaPool::orderBy('region_code')->orderBy('bundle_type')->get();
        $rawBalance = Cache::get('tencent:balance');
        $balanceDivisor = max(1, (int) config('services.tencent.balance_divisor', 100));
        $balanceUsd = $rawBalance === null
            ? Cache::get('tencent:balance:last')
            : round((float) $rawBalance / $balanceDivisor, 2);
        $balanceCheckedAt = Cache::get('tencent:balance:checked_at');
        $balanceThreshold = (float) config('services.tencent.low_balance_usd', 20);
        $quotaGuardMode = app(SettingsService::class)->get('quota_guard_mode', 'observe');
        $reconcileReport = Cache::get('reconcile:last_report');

        $taskStatus = (string) $request->query('status', 'all');
        $taskKind = (string) $request->query('kind', 'all');

        $tasksQuery = ProvisioningTask::with(['order.vpsSpec', 'vpsInstance', 'actor'])->latest('id');
        if ($taskStatus !== 'all' && in_array($taskStatus, ['pending', 'running', 'succeeded', 'failed', 'cancelled'], true)) {
            $tasksQuery->where('status', $taskStatus);
        }
        if ($taskKind !== 'all' && $taskKind !== '') {
            $tasksQuery->where('kind', $taskKind);
        }
        $tasks = $tasksQuery->paginate(20, ['*'], 'tasks_page')->withQueryString();
        $taskStats = [
            'pending_running' => ProvisioningTask::whereIn('status', ['pending', 'running'])->count(),
            'failed' => ProvisioningTask::where('status', 'failed')->count(),
            'succeeded' => ProvisioningTask::where('status', 'succeeded')->count(),
        ];

        $renewPendingInstances = VpsInstance::where('provider', 'tencent')
            ->get()
            ->filter(fn (VpsInstance $i) => ! empty($i->provider_meta['renew_pending']));

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->orderByDesc('id')->limit(50)->get()
            : collect();

        return view('admin.monitoring', compact(
            'tab',
            'statusCounts', 'problemInstances', 'staleProvisioning', 'slaBreaches', 'recentTransitions',
            'quotaPools', 'balanceUsd', 'balanceCheckedAt', 'balanceThreshold', 'quotaGuardMode',
            'reconcileReport', 'tasks', 'taskStatus', 'taskKind', 'taskStats', 'renewPendingInstances', 'failedJobs'
        ));
    }

    public function refreshBalance(BalanceGuard $guard)
    {
        try {
            $usd = $guard->balanceUsd(fresh: true);
            $this->audit('upstream.balance_refreshed', 'Memperbarui saldo deposit upstream: $' . number_format($usd, 2) . ' USD.');

            return redirect()->route('admin.monitoring', ['tab' => 'upstream'])
                ->with('success', 'Saldo upstream berhasil disegarkan: $' . number_format($usd, 2) . ' USD.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.monitoring', ['tab' => 'upstream'])
                ->with('error', 'Gagal membaca saldo upstream: ' . Str::limit($e->getMessage(), 180));
        }
    }

    public function runReconcile()
    {
        try {
            Artisan::call('provider:reconcile', ['--auto-correct' => true]);
            $report = Cache::get('reconcile:last_report');
            $this->audit('upstream.reconcile_triggered', 'Menjalankan rekonsiliasi manual upstream provider.', null, $report ?: []);

            $msg = is_array($report)
                ? "Rekonsiliasi selesai. Dicek: {$report['checked_count']}, Divergen: {$report['divergent_count']}, Orphan: " . count($report['orphans'] ?? []) . '.'
                : 'Rekonsiliasi upstream selesai dijalankan.';

            return redirect()->route('admin.monitoring', ['tab' => 'upstream'])->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('admin.monitoring', ['tab' => 'upstream'])
                ->with('error', 'Gagal menjalankan rekonsiliasi: ' . Str::limit($e->getMessage(), 180));
        }
    }

    public function retryTask(int $id)
    {
        $task = ProvisioningTask::findOrFail($id);

        if ($task->status !== 'failed') {
            return back()->with('error', "Task #{$task->id} berstatus '{$task->status}'. Hanya task 'failed' yang bisa di-retry.");
        }

        $jobClass = match ($task->kind) {
            'provision' => \App\Jobs\Provisioning\ProvisionVpsJob::class,
            'install_stack' => \App\Jobs\Provisioning\InstallStackJob::class,
            'reinstall' => \App\Jobs\Provisioning\ReinstallVpsJob::class,
            'renew' => \App\Jobs\Provisioning\RenewVpsJob::class,
            'upgrade' => \App\Jobs\Provisioning\UpgradeBundleJob::class,
            'destroy' => \App\Jobs\Provisioning\DestroyVpsJob::class,
            'stop' => \App\Jobs\Provisioning\StopVpsJob::class,
            'start' => \App\Jobs\Provisioning\StartVpsJob::class,
            'reboot', 'force_reboot' => \App\Jobs\Provisioning\RebootVpsJob::class,
            default => null,
        };

        if (! $jobClass) {
            return back()->with('error', "Jenis task '{$task->kind}' tidak mendukung retry otomatis.");
        }

        $task->update([
            'status' => 'pending',
            'error' => null,
            'attempts' => 0,
            'finished_at' => null,
            'current_step' => 'Dijadwalkan ulang oleh admin',
            'actor_user_id' => Auth::id(),
        ]);

        if ($task->order && $task->order->status === 'failed') {
            try {
                $this->orderStateMachine->transition($task->order, 'provisioning', [
                    'reason' => "Admin retry task #{$task->id} ({$task->kind}).",
                    'actor_type' => 'admin',
                ]);
            } catch (\Throwable) {
                // ignore
            }
        }

        $jobClass::dispatch($task->id);

        $this->audit('task.retried', "Menjalankan ulang task #{$task->id} ({$task->kind}).", $task);

        return redirect()->route('admin.monitoring', ['tab' => 'tasks'])
            ->with('success', "Task #{$task->id} ({$task->kind}) berhasil di-enqueue ulang.");
    }

    public function cancelTask(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ], [
            'reason.required' => 'Alasan pembatalan task wajib diisi.',
        ]);

        $task = ProvisioningTask::findOrFail($id);

        if (! in_array($task->status, ['pending', 'running'], true)) {
            return back()->with('error', "Hanya task 'pending' atau 'running' yang dapat dibatalkan.");
        }

        $task->update([
            'status' => 'cancelled',
            'error' => 'Dibatalkan admin: ' . $validated['reason'],
            'finished_at' => now(),
            'current_step' => 'Dibatalkan oleh admin',
            'actor_user_id' => Auth::id(),
        ]);

        $this->audit('task.cancelled', "Membatalkan task #{$task->id} ({$task->kind}): {$validated['reason']}", $task);

        return redirect()->route('admin.monitoring', ['tab' => 'tasks'])
            ->with('success', "Task #{$task->id} berhasil dibatalkan.");
    }

    public function retryStack(int $id)
    {
        $instance = VpsInstance::findOrFail($id);

        if ($instance->provider !== 'tencent' || ! $instance->provider_resource_id) {
            return back()->with('error', 'Retry stack otomatis hanya tersedia untuk instance Tencent yang sudah memiliki resource ID upstream.');
        }

        $task = ProvisioningTask::create([
            'order_id' => $instance->order_id,
            'vps_instance_id' => $instance->id,
            'kind' => 'install_stack',
            'status' => 'pending',
            'provider' => 'tencent',
            'progress' => 65,
            'current_step' => 'Menjadwalkan instalasi stack ulang',
            'actor_user_id' => Auth::id(),
            'input' => [
                'stack' => (string) ($instance->control_panel ?: 'none'),
            ],
        ]);

        \App\Jobs\Provisioning\InstallStackJob::dispatch($task->id);

        $this->audit('vps.retry_stack', "Dispatch ulang InstallStackJob (#{$task->id}) untuk {$instance->hostname}.", $instance);

        return back()->with('success', "Instalasi stack ulang untuk {$instance->hostname} dijadwalkan (Task #{$task->id}).");
    }

    public function retryRenew(int $id)
    {
        $instance = VpsInstance::findOrFail($id);

        if ($instance->provider !== 'tencent' || empty($instance->provider_meta['renew_pending'])) {
            return back()->with('error', 'Instance ini tidak memiliki status renew_pending Tencent.');
        }

        $latestRenewOrder = \App\Models\Order::where('vps_instance_id', $instance->id)
            ->where('order_type', 'renewal')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        $task = ProvisioningTask::create([
            'order_id' => $latestRenewOrder?->id ?? $instance->order_id,
            'vps_instance_id' => $instance->id,
            'kind' => 'renew',
            'status' => 'pending',
            'provider' => 'tencent',
            'progress' => 10,
            'current_step' => 'Menjadwalkan perpanjangan upstream ulang',
            'actor_user_id' => Auth::id(),
            'input' => ['months' => 1],
        ]);

        \App\Jobs\Provisioning\RenewVpsJob::dispatch($task->id);

        $this->audit('vps.retry_renew', "Dispatch ulang RenewVpsJob (#{$task->id}) untuk {$instance->hostname}.", $instance);

        return back()->with('success', "Perpanjangan upstream ulang untuk {$instance->hostname} dijadwalkan (Task #{$task->id}).");
    }

    public function retryFailedJob(string $uuid)
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->audit('queue.job_retried', "Menjalankan queue:retry untuk job {$uuid}.");

        return redirect()->route('admin.monitoring', ['tab' => 'failed-jobs'])
            ->with('success', "Failed job {$uuid} dijadwalkan ulang.");
    }

    public function deleteFailedJob(string $uuid)
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        $this->audit('queue.job_deleted', "Menghapus failed job {$uuid} via queue:forget.");

        return redirect()->route('admin.monitoring', ['tab' => 'failed-jobs'])
            ->with('success', "Failed job {$uuid} berhasil dihapus.");
    }
}
