<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Models\VpsInstance;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\VpsLifecycleDispatcher;
use App\Services\VpsStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InstanceController extends Controller
{
    use Concerns\LogsAdminAudit;

    private const PANEL_URL_MESSAGES = [
        'app_url.required' => 'Link control panel wajib diisi untuk paket dengan panel.',
        'app_url.url' => 'Link control panel harus lengkap dan diawali http:// atau https:// (contoh: http://103.150.10.2:8000 atau https://panel.domain.com).',
    ];

    public function __construct(
        protected VpsStateMachine $vpsStateMachine,
        protected OrderStateMachine $orderStateMachine,
        protected VpsLifecycleDispatcher $lifecycle
    ) {
    }

    public function instances(Request $request)
    {
        $provider = (string) $request->input('provider', 'all');
        $status = (string) $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));

        $query = VpsInstance::with(['customer', 'datacenterRegion', 'order.vpsSpec', 'latestProvisioningTask', 'activityLogs' => fn($q) => $q->latest()->take(5)])
            ->orderBy('created_at', 'desc');

        if ($provider === 'tencent') {
            $query->where('provider', 'tencent');
        } elseif ($provider === 'manual') {
            $query->where(function ($q) {
                $q->whereNull('provider')->orWhere('provider', '!=', 'tencent');
            });
        }

        if ($status !== 'all' && in_array($status, ['running', 'stopped', 'provisioning', 'reinstalling', 'rebooting', 'suspended', 'terminated', 'error'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('hostname', 'like', "%{$escapedSearch}%")
                  ->orWhere('public_ip', 'like', "%{$escapedSearch}%")
                  ->orWhere('private_ip', 'like', "%{$escapedSearch}%")
                  ->orWhere('provider_resource_id', 'like', "%{$escapedSearch}%")
                  ->orWhere('control_panel', 'like', "%{$escapedSearch}%")
                  ->orWhere('app_name', 'like', "%{$escapedSearch}%")
                  ->orWhereHas('customer', function ($cq) use ($escapedSearch) {
                      $cq->where('full_name', 'like', "%{$escapedSearch}%")
                         ->orWhere('username', 'like', "%{$escapedSearch}%")
                         ->orWhere('email', 'like', "%{$escapedSearch}%");
                  });
            });
        }

        $instances = $query->paginate(15)->withQueryString();

        $statusCounts = [
            'all' => VpsInstance::count(),
            'running' => VpsInstance::where('status', 'running')->count(),
            'stopped' => VpsInstance::where('status', 'stopped')->count(),
            'provisioning' => VpsInstance::where('status', 'provisioning')->count(),
            'suspended' => VpsInstance::where('status', 'suspended')->count(),
            'terminated' => VpsInstance::where('status', 'terminated')->count(),
            'error' => VpsInstance::where('status', 'error')->count(),
        ];

        $tencentCount = VpsInstance::where('provider', 'tencent')->count();
        $manualCount = max(0, $statusCounts['all'] - $tencentCount);
        $attentionCount = VpsInstance::whereIn('status', ['stopped', 'suspended', 'error', 'provisioning', 'reinstalling'])->count();

        $providerCounts = [
            'all' => $statusCounts['all'],
            'tencent' => $tencentCount,
            'manual' => $manualCount,
            'attention' => $attentionCount,
        ];

        $recentLogs = \App\Models\VpsActivityLog::with(['user', 'vpsInstance'])
            ->latest()
            ->take(15)
            ->get();

        $day2Service = app(\App\Services\Provisioning\VpsDay2Service::class);
        $day2Caps = [];
        foreach ($instances as $instance) {
            $caps = $day2Service->capabilities($instance);
            if (!empty($caps['capable'])) {
                $day2Caps[$instance->id] = $caps;
            }
        }

        return view('admin.instances', compact('instances', 'recentLogs', 'statusCounts', 'providerCounts', 'provider', 'status', 'search', 'day2Caps'));
    }

    public function updateInstanceStatus(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:running,stopped,provisioning,error,suspended,terminated',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $this->vpsStateMachine->transition($instance, $validated['status'], [
                'reason' => $validated['reason'] ?? 'Update status oleh Administrator.',
                'actor_type' => 'admin',
            ]);

            $instance->refresh()->logActivity(
                action: 'status_change',
                description: "Status VPS diubah ke {$validated['status']} oleh Administrator.",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal ubah status: ' . $e->getMessage());
        }

        return back()->with('success', 'Status VPS ' . ($instance->hostname ?? $instance->id) . ' berhasil diubah menjadi ' . $validated['status'] . '.');
    }

    public function updateInstancePanelUrl(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);

        $validated = $request->validate([
            'app_url' => ['nullable', 'url:http,https', 'max:255'],
        ], self::PANEL_URL_MESSAGES);

        $newUrl = $validated['app_url'] ?? null;
        $instance->update(['app_url' => $newUrl]);

        $instance->logActivity(
            action: 'panel_url_updated',
            description: $newUrl ? "Link control panel diperbarui tim: {$newUrl}" : 'Link control panel dikosongkan oleh tim.',
            status: 'completed',
            userId: Auth::id()
        );

        $serverName = $instance->hostname ?? ('#' . $instance->id);

        return back()->with('success', "Link control panel {$serverName} berhasil disimpan.");
    }

    public function updateInstanceSshUsername(Request $request, $id)
    {
        $validated = $request->validate([
            'ssh_username' => array_merge(['required'], array_slice(VpsInstance::SSH_USERNAME_RULE, 1)),
        ], VpsInstance::SSH_USERNAME_MESSAGES);

        $instance = VpsInstance::with('customer')->findOrFail($id);
        $serverName = $instance->hostname ?? ('#' . $instance->id);
        $oldUsername = $instance->ssh_user;
        $newUsername = VpsInstance::normalizeSshUsername($validated['ssh_username']);

        if ($oldUsername === $newUsername) {
            return back()->with('success', "Username SSH {$serverName} tidak berubah ({$newUsername}).");
        }

        $instance->update(['ssh_username' => $newUsername]);

        $instance->logActivity(
            action: 'ssh_username_updated',
            description: "Username SSH diperbarui tim: {$oldUsername} → {$newUsername}",
            status: 'completed',
            userId: Auth::id()
        );

        try {
            $instance->customer?->notify(new \App\Notifications\VpsSshUsernameChangedNotification($instance->fresh(), $oldUsername));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.ssh_username.notify_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', "Username SSH {$serverName} diubah ke {$newUsername}. Pelanggan sudah diberi tahu.");
    }

    public function updateInstanceRenewalPrice(Request $request, $id)
    {
        $validated = $request->validate([
            'custom_renewal_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $instance = VpsInstance::findOrFail($id);
        $newPrice = $request->filled('custom_renewal_price') ? (float) $validated['custom_renewal_price'] : null;

        $instance->update([
            'custom_renewal_price' => $newPrice,
        ]);

        $serverName = $instance->hostname ?? ('#' . $instance->id);
        $msg = $newPrice !== null
            ? "Tarif perpanjangan khusus server {$serverName} berhasil diatur ke Rp " . number_format($newPrice, 0, ',', '.') . "/bln."
            : "Tarif perpanjangan server {$serverName} dikembalikan mengikuti katalog umum.";

        $instance->logActivity('custom_renewal_price_updated', $msg, 'completed', Auth::id());

        return back()->with('success', $msg);
    }

    public function suspendInstance(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);
        $reason = $request->input('reason', 'Penangguhan layanan oleh Administrator.');

        if ($this->lifecycle->supports($instance)) {
            try {
                $this->lifecycle->suspend($instance, $reason);
                $instance->refresh()->logActivity(
                    action: 'suspend',
                    description: "VPS ditangguhkan oleh Administrator (stop upstream diproses). Alasan: {$reason}",
                    status: 'pending',
                    userId: Auth::id()
                );
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal suspend: ' . $e->getMessage());
            }

            return back()->with('success', "VPS {$instance->hostname} sedang ditangguhkan (server dimatikan di upstream terlebih dahulu).");
        }

        try {
            $this->vpsStateMachine->transition($instance, 'suspended', [
                'reason' => $reason,
                'actor_type' => 'admin',
            ]);

            $instance->refresh()->logActivity(
                action: 'suspend',
                description: "VPS ditangguhkan oleh Administrator. Alasan: {$reason}",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal suspend: ' . $e->getMessage());
        }

        return back()->with('success', "VPS {$instance->hostname} berhasil ditangguhkan (Suspended).");
    }

    public function unsuspendInstance(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);
        $reason = $request->input('reason', 'Penangguhan dicabut oleh Administrator.');

        if ($this->lifecycle->supports($instance)) {
            try {
                $this->lifecycle->unsuspend($instance, $reason);
                $instance->refresh()->logActivity(
                    action: 'unsuspend',
                    description: "Penangguhan VPS dicabut oleh Administrator (pemulihan upstream diproses).",
                    status: 'pending',
                    userId: Auth::id()
                );
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal unsuspend: ' . $e->getMessage());
            }

            return back()->with('success', "Penangguhan VPS {$instance->hostname} sedang dicabut (server diaktifkan kembali di upstream).");
        }

        try {
            $this->vpsStateMachine->transition($instance, 'running', [
                'reason' => $reason,
                'actor_type' => 'admin',
            ]);

            $instance->refresh()->logActivity(
                action: 'unsuspend',
                description: "Penangguhan VPS dicabut oleh Administrator. Status kembali Running.",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal unsuspend: ' . $e->getMessage());
        }

        return back()->with('success', "Penangguhan VPS {$instance->hostname} berhasil dicabut. Server kembali aktif (Running).");
    }

    public function terminateInstance(Request $request, $id)
    {
        $instance = VpsInstance::with('order')->findOrFail($id);
        $reason = $request->input('reason', 'Terminasi permanen oleh Administrator.');

        if ($this->lifecycle->supports($instance)) {
            try {
                $this->lifecycle->destroy($instance, $reason);
                $instance->refresh()->logActivity(
                    action: 'terminate',
                    description: "Terminasi permanen oleh Administrator (destroy upstream diproses). Alasan: {$reason}",
                    status: 'pending',
                    userId: Auth::id()
                );
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal terminate: ' . $e->getMessage());
            }

            return back()->with('success', "VPS {$instance->hostname} sedang di-terminasi (penghapusan upstream diproses aman dua tahap).");
        }

        try {
            DB::transaction(function () use ($instance, $reason) {
                $this->vpsStateMachine->transition($instance, 'terminated', [
                    'reason' => $reason,
                    'actor_type' => 'admin',
                ]);

                if ($instance->order && $instance->order->status !== 'terminated') {
                    try {
                        $this->orderStateMachine->transition($instance->order, 'terminated', [
                            'reason' => "Terkait terminasi VPS #{$instance->id}. Alasan: {$reason}",
                            'actor_type' => 'admin',
                        ]);
                    } catch (InvalidStateTransitionException $e) {
                        // ignore
                    }
                }
            });

            $instance->refresh()->logActivity(
                action: 'terminate',
                description: "VPS dan alokasi resource di-terminasi permanen. Alasan: {$reason}",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal terminate: ' . $e->getMessage());
        }

        return back()->with('success', "VPS {$instance->hostname} berhasil di-terminasi (Terminated).");
    }

    public function syncUpstream($id)
    {
        $instance = VpsInstance::findOrFail($id);

        if (! $this->lifecycle->supports($instance)) {
            return back()->with('error', 'Instance ini dikelola manual dan belum mendukung sinkronisasi upstream.');
        }

        try {
            $provider = app(\App\Services\Provisioning\ProviderManager::class)->provider((string) $instance->provider);
            $result = $provider->status($instance);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal sinkronisasi upstream: ' . $e->getMessage());
        }

        $providerStatus = $result['status'] ?? null;
        $meta = $result['meta'] ?? [];

        $updates = [
            'last_reconciled_at' => now(),
            'provider_meta' => array_merge($instance->provider_meta ?? [], [
                'last_reconcile' => $meta,
            ]),
        ];

        if (! empty($meta['public_ip']) && $meta['public_ip'] !== $instance->public_ip) {
            $updates['public_ip'] = $meta['public_ip'];
        }
        if (! empty($meta['private_ip']) && $meta['private_ip'] !== $instance->private_ip) {
            $updates['private_ip'] = $meta['private_ip'];
        }

        $instance->update($updates);

        if ($providerStatus && $providerStatus !== $instance->status) {
            try {
                $this->vpsStateMachine->transition($instance, $providerStatus, [
                    'reason' => 'Sinkronisasi manual dari upstream oleh Administrator.',
                    'actor_type' => 'admin',
                    'metadata' => ['source' => 'admin_sync_upstream'],
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $instance->refresh()->logActivity(
            action: 'sync_upstream',
            description: 'Status dan IP upstream disinkronkan oleh Administrator (' . ($providerStatus ?: $instance->status) . ').',
            status: 'completed',
            userId: Auth::id()
        );

        $this->audit('vps.sync_upstream', "Sinkronisasi status upstream VPS #{$instance->id}", $instance, [
            'provider_status' => $providerStatus,
            'public_ip' => $meta['public_ip'] ?? $instance->public_ip,
        ]);

        return back()->with('success', "Status upstream {$instance->hostname} berhasil disinkronkan (" . strtoupper((string) ($providerStatus ?: $instance->status)) . ').');
    }
}
