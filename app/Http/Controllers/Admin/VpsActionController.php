<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Jobs\Provisioning\ReinstallVpsJob;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\Provisioning\Exceptions\Day2Exception;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\Provisioning\VpsDay2Service;
use App\Services\Provisioning\VpsLifecycleDispatcher;
use App\Services\VpsStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Phase 5D — admin mirror of the customer Day-2 actions. Same VpsDay2Service
 * engine, but instances are resolved without org scope (admins oversee all
 * tenants) and every mutating action leaves an AdminAuditLog trail.
 *
 * The VNC console is audited WITHOUT the URL — the single-use link must never
 * be persisted, including in audit logs.
 */
class VpsActionController extends Controller
{
    use LogsAdminAudit;

    public function __construct(private readonly VpsDay2Service $day2)
    {
    }

    private function instance(int $id): VpsInstance
    {
        return VpsInstance::findOrFail($id);
    }

    public function capabilities(int $id): JsonResponse
    {
        return response()->json(['ok' => true] + $this->day2->capabilities($this->instance($id)));
    }

    /** Day-2 task polling for the admin mirror (raw error visible to admin). */
    public function status(int $id): JsonResponse
    {
        $vps = $this->instance($id)->load('latestProvisioningTask');
        $task = $vps->latestProvisioningTask;

        return response()->json([
            'ok' => true,
            'vps_id' => $vps->id,
            'vps_status' => $vps->status,
            'task' => $task ? [
                'id' => $task->id,
                'kind' => $task->kind,
                'status' => $task->status,
                'progress' => (int) $task->progress,
                'current_step' => $task->current_step,
                'error' => $task->error,
            ] : null,
        ]);
    }

    public function power(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'stop', 'reboot', 'force_reboot'])],
        ]);
        $instance = $this->instance($id);

        return $this->guard(function () use ($instance, $data) {
            $result = $this->day2->power($instance, $data['action']);
            $this->audit('vps.power', 'Aksi power admin: '.$data['action'], $instance, ['action' => $data['action'], 'task_id' => $result['task_id']]);

            return $result;
        });
    }

    public function resetPassword(int $id): JsonResponse
    {
        $instance = $this->instance($id);

        return $this->guard(function () use ($instance) {
            $result = $this->day2->resetPassword($instance);
            $this->audit('vps.reset_password', 'Reset password server oleh admin', $instance, ['task_id' => $result['task_id']]);

            return $result;
        });
    }

    public function reinstall(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $instance = $this->instance($id);

        if (! app(VpsLifecycleDispatcher::class)->supports($instance)) {
            return $this->reinstallError($request, 'Server ini dikelola manual dan tidak mendukung reinstall otomatis.', 422);
        }

        $stackRegistry = app(StackRegistry::class);
        $osKeys = array_keys(Order::osLabels());
        $stackKeys = $stackRegistry->keys();

        $data = $request->validate([
            'os' => ['required', 'string', Rule::in($osKeys)],
            'control_panel' => ['required', 'string', Rule::in($stackKeys)],
            'confirm_hostname' => ['required', 'string', 'max:100'],
        ]);

        $serverName = (string) ($instance->hostname ?: 'vps-'.$instance->id);
        if (strcasecmp(trim($data['confirm_hostname']), $serverName) !== 0) {
            return $this->reinstallError($request, "Konfirmasi hostname tidak cocok. Ketik persis: {$serverName}", 422, 'confirm_hostname');
        }

        if (! $stackRegistry->supportsOs($data['os'], $data['control_panel'])) {
            return $this->reinstallError($request, 'Kombinasi OS dan stack tidak didukung untuk reinstall otomatis.', 422, 'control_panel');
        }

        $hasLive = ProvisioningTask::where('vps_instance_id', $instance->id)
            ->where('kind', 'reinstall')
            ->whereIn('status', ['pending', 'running'])
            ->exists();

        if ($hasLive) {
            return $this->reinstallError($request, 'Proses reinstall sebelumnya masih berjalan pada server ini.', 409);
        }

        $task = ProvisioningTask::create([
            'order_id' => $instance->order_id,
            'vps_instance_id' => $instance->id,
            'kind' => 'reinstall',
            'status' => 'pending',
            'provider' => (string) ($instance->provider ?? 'tencent'),
            'input' => [
                'os' => $data['os'],
                'control_panel' => $data['control_panel'],
                'initiated_by' => 'admin',
                'admin_id' => Auth::id(),
            ],
        ]);

        try {
            app(VpsStateMachine::class)->transition($instance, 'reinstalling', [
                'reason' => 'Reinstall otomatis dimulai oleh Administrator.',
                'actor_type' => 'admin',
                'allowSame' => true,
            ]);
        } catch (\Throwable $e) {
            // Best-effort UI status; job remains dispatched.
        }

        ReinstallVpsJob::dispatch($task->id);

        try {
            $instance->logActivity(
                action: 'reinstall_requested',
                description: "Admin memulai reinstall OS otomatis ({$data['os']}, stack {$data['control_panel']}).",
                status: 'pending',
                userId: Auth::id()
            );
        } catch (\Throwable $e) {
            // Ignore activity log failure
        }

        $this->audit('vps.reinstall', 'Reinstall OS otomatis dimulai oleh admin', $instance, [
            'task_id' => $task->id,
            'os' => $data['os'],
            'control_panel' => $data['control_panel'],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'task_id' => $task->id, 'status' => 'reinstalling']);
        }

        return back()->with('success', "Reinstall otomatis {$serverName} dimulai (Task #{$task->id}).");
    }

    private function reinstallError(Request $request, string $message, int $status, string $field = 'confirm_hostname'): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => $message], $status);
        }

        return back()->withInput()->withErrors([$field => $message])->with('error', $message);
    }

    public function console(int $id): JsonResponse
    {
        $instance = $this->instance($id);

        return $this->guard(function () use ($instance) {
            $result = $this->day2->console($instance);
            // Audit the access, NEVER the single-use URL.
            $this->audit('vps.console', 'Membuka konsol server (VNC) sebagai admin', $instance);

            return $result;
        });
    }

    public function firewallIndex(int $id): JsonResponse
    {
        return $this->guard(fn () => ['rules' => $this->day2->firewallRules($this->instance($id))]);
    }

    public function firewallStore(Request $request, int $id): JsonResponse
    {
        $data = $this->validateFirewall($request);
        $instance = $this->instance($id);

        return $this->guard(function () use ($instance, $data) {
            $result = $this->day2->addFirewallRule($instance, $data);
            $this->audit('vps.firewall.add', 'Menambah aturan firewall', $instance, ['rule' => $data]);

            return $result;
        });
    }

    public function firewallDestroy(Request $request, int $id): JsonResponse
    {
        $data = $this->validateFirewall($request);
        $confirmSsh = $request->boolean('confirm_ssh');
        $instance = $this->instance($id);

        return $this->guard(function () use ($instance, $data, $confirmSsh) {
            $result = $this->day2->deleteFirewallRule($instance, $data, $confirmSsh);
            $this->audit('vps.firewall.delete', 'Menghapus aturan firewall', $instance, ['rule' => $data, 'confirm_ssh' => $confirmSsh]);

            return $result;
        });
    }

    public function traffic(int $id): JsonResponse
    {
        return $this->guard(fn () => $this->day2->traffic($this->instance($id)));
    }

    public function upgradePreview(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['target_spec_id' => ['required', 'integer']]);
        $target = VpsSpec::findOrFail($data['target_spec_id']);

        return $this->guard(fn () => $this->day2->upgradePreview($this->instance($id), $target));
    }

    public function upgrade(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['target_spec_id' => ['required', 'integer']]);
        $target = VpsSpec::findOrFail($data['target_spec_id']);
        $instance = $this->instance($id);

        return $this->guard(function () use ($instance, $target) {
            $result = $this->day2->createUpgrade($instance, $target);
            $this->audit('vps.upgrade', 'Membuat order upgrade server', $instance, [
                'target_spec_id' => $target->id,
                'order_id' => $result['order_id'] ?? null,
            ]);

            return $result;
        });
    }

    // =====================================================================

    private function validateFirewall(Request $request): array
    {
        return $request->validate([
            'protocol' => ['required', Rule::in(['TCP', 'UDP', 'ICMP', 'ALL', 'tcp', 'udp', 'icmp', 'all'])],
            'port' => ['required', 'string', 'max:60', 'regex:/^\d{1,5}(-\d{1,5})?(,\d{1,5}(-\d{1,5})?)*$/'],
            'cidr' => ['nullable', 'string', 'max:45', 'regex:/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/'],
            'action' => ['nullable', Rule::in(['ACCEPT', 'DROP', 'accept', 'drop'])],
            'description' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + $fn());
        } catch (Day2Exception $e) {
            $body = ['ok' => false, 'code' => $e->errorCode, 'error' => $e->getMessage()];
            if ($e->replacementSpecId !== null) {
                $body['replacement_spec_id'] = $e->replacementSpecId;
            }

            return response()->json($body, 422);
        } catch (ProviderException $e) {
            Log::warning('admin.day2.provider_error', [
                'code' => $e->providerCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'code' => $e->providerCode ?: 'upstream_error',
                'error' => 'Gagal memuat data dari upstream: ' . $e->getMessage(),
            ], 502);
        }
    }
}