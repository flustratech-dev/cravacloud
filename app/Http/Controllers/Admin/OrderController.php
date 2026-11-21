<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Models\VpsStatusHistory;
use App\Services\OrderStateMachine;
use App\Services\VpsStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use Concerns\LogsAdminAudit;

    private const PANEL_URL_MESSAGES = [
        'app_url.required' => 'Link control panel wajib diisi untuk paket dengan panel.',
        'app_url.url' => 'Link control panel harus lengkap dan diawali http:// atau https:// (contoh: http://103.150.10.2:8000 atau https://panel.domain.com).',
    ];

    public function __construct(
        protected OrderStateMachine $orderStateMachine,
        protected VpsStateMachine $vpsStateMachine
    ) {
    }

    public function orders(Request $request)
    {
        if ($request->query('tab') === 'shopee') {
            return redirect()->route('admin.shopee');
        }

        $query = Order::with(['customer', 'vpsSpec', 'invoice', 'provisioningTasks' => fn ($q) => $q->latest('id')])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            if ($request->status === 'needs_provision') {
                $query->whereIn('status', ['paid', 'provisioning'])->whereDoesntHave('vpsInstance');
            } elseif ($request->status === 'failed') {
                $query->where(function ($q) {
                    $q->where('status', 'failed')
                        ->orWhere(function ($q2) {
                            $q2->where('status', 'provisioning')
                                ->where('updated_at', '<=', now()->subMinutes(20));
                        });
                });
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        $orders = $query->paginate(15)->withQueryString();
        $specs = VpsSpec::all();

        $needsProvisionCount = Order::whereIn('status', ['paid', 'provisioning'])
            ->whereDoesntHave('vpsInstance')
            ->count();
        $failedCount = Order::where('status', 'failed')->count();

        return view('admin.orders', compact('orders', 'specs', 'needsProvisionCount', 'failedCount'));
    }

    public function markOrderPaid(Request $request, $id)
    {
        $validated = $request->validate([
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::with('invoice')->findOrFail($id);

        try {
            DB::transaction(function () use ($order, $validated) {
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice?->id,
                    'provider' => 'manual',
                    'provider_transaction_id' => 'MANUAL-' . Str::upper(Str::random(10)),
                    'provider_order_ref' => 'ORDER-' . $order->id,
                    'payment_method' => $order->payment_method ?? 'manual',
                    'amount' => $order->amount,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'settled_at' => now(),
                    'raw_payload' => [
                        'reference' => $validated['reference'] ?? null,
                        'note' => $validated['note'] ?? null,
                        'admin_user_id' => Auth::id(),
                    ],
                ]);

                $this->orderStateMachine->transition(
                    $order,
                    'paid',
                    [
                        'reason' => 'Admin manual mark paid. ' . ($validated['note'] ?? ''),
                        'actor_type' => 'admin',
                        'metadata' => [
                            'reference' => $validated['reference'] ?? null,
                            'transaction_id' => $tx->id,
                        ],
                        'onLocked' => function (Order $locked) use ($tx) {
                            $locked->paid_at = now();
                            $locked->save();

                            if ($locked->invoice) {
                                $locked->invoice->update([
                                    'status' => 'paid',
                                    'paid_at' => now(),
                                    'paid_via_transaction_id' => $tx->id,
                                ]);
                            }
                        },
                    ]
                );
            });

            app(\App\Services\PaymentCompletionService::class)->handle($order->id);
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal mark paid: ' . $e->getMessage());
        }

        return back()->with('success', "Order #{$order->id} berhasil di-mark paid. Silakan lanjutkan provisioning.");
    }

    public function provision(Request $request, $id)
    {
        $order = Order::with(['vpsSpec', 'invoice'])->findOrFail($id);

        if (!in_array($order->status, ['paid', 'failed'], true)) {
            return back()->with('error',
                "Order #{$order->id} masih status [{$order->status}]. Provisioning hanya bisa dari status 'paid' atau retry 'failed'.");
        }

        if ($order->status === 'paid' && $order->vpsInstance) {
            return back()->with('error', "Order #{$order->id} sudah memiliki VpsInstance terkait.");
        }

        $hasPanel = $order->control_panel !== 'none';

        $rules = [
            'public_ip' => 'required|ip',
            'private_ip' => 'nullable|ip',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_username' => VpsInstance::SSH_USERNAME_RULE,
            'root_password' => 'required|string|min:8|max:128',
        ];

        $rules['app_url'] = [$hasPanel ? 'required' : 'nullable', 'url:http,https', 'max:255'];

        $validated = $request->validate($rules, self::PANEL_URL_MESSAGES + VpsInstance::SSH_USERNAME_MESSAGES);

        try {
            $instance = DB::transaction(function () use ($order, $validated, $hasPanel) {
                $this->orderStateMachine->transition($order, 'provisioning', [
                    'reason' => 'Admin memulai provisioning.',
                    'actor_type' => 'admin',
                    'onLocked' => function (Order $locked) {
                        $locked->provisioning_attempts = ($locked->provisioning_attempts ?? 0) + 1;
                        $locked->failure_reason = null;
                        $locked->save();
                    },
                ]);
                $order->refresh();

                $spec = $order->vpsSpec;
                $startsAt = $order->provisioningStartsAt();
                $months = match ($order->billing_cycle) {
                    'quarterly' => 3,
                    'semi_annual' => 6,
                    'annual' => 12,
                    default => 1,
                };
                $expiresAt = $startsAt->copy()->addMonths($months);
                $graceEndsAt = $expiresAt->copy()->addDays(app(\App\Services\RenewalService::class)->graceDaysForOrder($order));

                $hostname = $order->hostname ?: ('vps-' . $order->id);
                $os = $order->os ?: 'Ubuntu 24.04 LTS';
                $rootPassword = $validated['root_password'];
                $appUrl = $hasPanel ? ($validated['app_url'] ?? null) : null;

                $instance = VpsInstance::create([
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'organization_id' => $order->organization_id,
                    'hostname' => $hostname,
                    'public_ip' => $validated['public_ip'],
                    'private_ip' => $validated['private_ip'] ?? null,
                    'ssh_port' => $validated['ssh_port'] ?? 22,
                    'ssh_username' => VpsInstance::normalizeSshUsername($validated['ssh_username'] ?? null),
                    'initial_root_password' => $rootPassword,
                    'os' => $os,
                    'datacenter_location' => $order->datacenter_location ?? 'ID-CGK01',
                    'status' => 'provisioning',
                    'cpu' => $spec->cpu ?? 2,
                    'ram' => $spec->ram ?? 4,
                    'disk' => $spec->disk ?? 60,
                    'control_panel' => $order->control_panel,
                    'provider' => $order->provider ?? 'manual',
                    'app_url' => $appUrl,
                    'uptime_percent' => 99.98,
                    'billing_cycle' => $order->billing_cycle ?? 'monthly',
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'grace_period_ends_at' => $graceEndsAt,
                    'auto_renew' => true,
                ]);

                VpsStatusHistory::create([
                    'vps_instance_id' => $instance->id,
                    'from_status' => null,
                    'to_status' => 'provisioning',
                    'reason' => 'VPS instance dibuat.',
                    'actor_user_id' => Auth::id(),
                    'actor_type' => 'admin',
                    'ip_address' => request()->ip(),
                    'metadata' => ['order_id' => $order->id],
                    'created_at' => now(),
                ]);

                $this->vpsStateMachine->transition($instance, 'running', [
                    'reason' => 'VPS sukses dikonfigurasi.',
                    'actor_type' => 'admin',
                ]);

                $this->orderStateMachine->transition($order, 'active', [
                    'reason' => 'Provisioning sukses.',
                    'actor_type' => 'admin',
                    'metadata' => ['vps_instance_id' => $instance->id],
                    'onLocked' => function (Order $locked) use ($startsAt, $expiresAt, $graceEndsAt, $rootPassword) {
                        $locked->starts_at = $startsAt;
                        $locked->expires_at = $expiresAt;
                        $locked->grace_period_ends_at = $graceEndsAt;
                        $locked->root_password = $rootPassword;
                        $locked->save();
                    },
                ]);

                $instance->refresh()->logActivity(
                    action: 'provision',
                    description: "VPS {$instance->hostname} (IP: {$instance->public_ip}) berhasil di-provision oleh Administrator.",
                    status: 'completed',
                    userId: Auth::id()
                );

                if ($order->setupSlaBreached()) {
                    $instance->logActivity(
                        action: 'sla_compensation',
                        description: 'Setup melewati batas ' . Order::SETUP_SLA_HOURS . ' jam sejak pembayaran. '
                            . 'Sebagai kompensasi, masa aktif dihitung sejak server diserahkan.',
                        status: 'completed',
                        userId: Auth::id()
                    );
                }

                if ($order->invoice && $order->invoice->status !== 'paid') {
                    $order->invoice->update(['status' => 'paid', 'paid_at' => now()]);
                }

                app(\App\Services\SubscriptionSyncService::class)->ensureForInstance($instance->fresh());

                return $instance;
            });
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Provisioning gagal: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $order->refresh();
            try {
                if ($order->status === 'provisioning') {
                    $this->orderStateMachine->transition($order, 'failed', [
                        'reason' => 'Provisioning exception: ' . $e->getMessage(),
                        'actor_type' => 'system',
                        'metadata' => ['error' => $e->getMessage()],
                        'onLocked' => function (Order $locked) use ($e) {
                            $locked->failure_reason = mb_substr($e->getMessage(), 0, 500);
                            $locked->save();
                        },
                    ]);
                }
            } catch (\Throwable $inner) {
                // ignore
            }
            return back()->with('error', 'Provisioning error: ' . $e->getMessage());
        }

        try {
            $order->loadMissing(['customer', 'vpsSpec']);
            if ($order->customer) {
                $order->customer->notify(new \App\Notifications\VpsProvisionedNotification($instance));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.provision.notify_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success',
            "VPS {$instance->hostname} (IP: {$validated['public_ip']}) berhasil di-provision. Status order diubah ke Active.");
    }

    public function retryProvision(Request $request, $id)
    {
        $order = Order::with(['vpsSpec', 'vpsInstance', 'datacenterRegion'])->findOrFail($id);
        if ($order->status !== 'failed') {
            return back()->with('error', "Retry hanya untuk order status 'failed'. Status sekarang: {$order->status}.");
        }

        $spec = $order->vpsSpec;
        $gate = app(\App\Services\Provisioning\ProvisioningGate::class);
        if ($spec && $spec->hasUpstreamMapping() && $gate->allows($spec) && empty($order->metadata['manual_fulfillment'])) {
            try {
                $task = app(\App\Services\Provisioning\ProvisioningOrchestrator::class)->retryFailedOrder($order);
                $this->audit('order.retry_provision', "Menjalankan retry auto-provisioning Order #{$order->id} (Task #{$task->id}).", $order);

                return back()->with('success', "Retry auto-provisioning Order #{$order->id} dijadwalkan (Task #{$task->id}).");
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal retry auto-provisioning: ' . $e->getMessage());
            }
        }

        try {
            $this->orderStateMachine->transition($order, 'provisioning', [
                'reason' => 'Admin retry provisioning setelah gagal.',
                'actor_type' => 'admin',
                'onLocked' => function (Order $locked) {
                    $locked->failure_reason = null;
                    $locked->save();
                },
            ]);
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal retry: ' . $e->getMessage());
        }

        $this->audit('order.retry_provision', "Membuka retry manual Order #{$order->id}.", $order);

        return back()->with('info', "Order #{$order->id} disiapkan untuk retry. Silakan buka form provisioning.");
    }

    public function syncMidtrans(int $id)
    {
        $order = Order::with(['customer', 'vpsSpec', 'invoice'])->findOrFail($id);
        $res = \App\Services\Payments\MidtransService::checkAndSyncStatus($order);

        $this->audit('order.midtrans_synced', "Mengecek status Midtrans untuk Order #{$order->id}: " . ($res['message'] ?? ($res['status'] ?? 'done')), $order);

        if (! empty($res['success'])) {
            return back()->with('success', "Status Midtrans Order #{$order->id} disinkronkan: lunas dan diperbarui.");
        }

        return back()->with('info', "Status Midtrans Order #{$order->id}: " . ($res['message'] ?? 'Masih pending / belum settled.'));
    }

    public function orderHistory($id)
    {
        $order = Order::with(['customer', 'vpsSpec'])->findOrFail($id);
        $histories = OrderStatusHistory::where('order_id', $order->id)
            ->with('actor')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.order-history', compact('order', 'histories'));
    }

    public function cancelOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $reason = $request->input('reason', 'Dibatalkan oleh Administrator.');

        try {
            $this->orderStateMachine->transition($order, 'cancelled', [
                'reason' => $reason,
                'actor_type' => 'admin',
                'onLocked' => function (Order $locked) {
                    if ($locked->invoice && $locked->invoice->status !== 'cancelled') {
                        $locked->invoice->update(['status' => 'cancelled']);
                    }
                },
            ]);
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', "Order #{$order->id} tidak bisa dibatalkan: " . $e->getMessage());
        }

        return back()->with('success', 'Order #' . $order->id . ' berhasil dibatalkan.');
    }
}
