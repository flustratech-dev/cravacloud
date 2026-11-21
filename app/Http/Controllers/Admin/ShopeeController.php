<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Models\VpsStatusHistory;
use App\Services\OrderStateMachine;
use App\Services\VpsStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShopeeController extends Controller
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

    public function showShopeeForm(Request $request)
    {
        $specs = VpsSpec::where('category', 'vps')
            ->where('is_active', true)
            ->orderBy('sell_price', 'asc')
            ->get();

        $query = Order::where('channel', 'shopee')
            ->with(['customer', 'vpsSpec', 'vpsInstance', 'invoice'])
            ->latest();

        if ($search = trim((string)$request->input('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('shopee_order_id', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('hostname', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vpsInstance', function ($iq) use ($search) {
                      $iq->where('public_ip', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'needs_provision') {
                $query->where('status', 'paid')->whereDoesntHave('vpsInstance');
            } elseif ($status === 'active') {
                $query->where('status', 'active');
            } elseif ($status === 'cancelled') {
                $query->where('status', 'cancelled');
            }
        }

        $recentShopeeOrders = $query->paginate(15)->withQueryString();
        $operatingSystems = Order::osLabels();
        $providerLabels = Order::providerLabels();
        $stackLabels = Order::stackLabels();

        return view('admin.shopee', compact(
            'specs',
            'recentShopeeOrders',
            'operatingSystems',
            'providerLabels',
            'stackLabels'
        ));
    }

    public function processShopeeOrder(Request $request)
    {
        $validated = $request->validate([
            'shopee_order_id' => 'required|string|max:100',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:25',
            'vps_spec_id' => 'required|exists:vps_specs,id',
            'provider' => 'nullable|string|max:50',
            'control_panel' => 'required|string',
            'datacenter_location' => 'required|in:singapore,indonesia',
            'os' => 'required|string',
            'hostname' => ['nullable', 'string', 'max:63', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'shopee_mode' => 'nullable|in:auto,manual',
            'auto_provision' => 'nullable|boolean',
            'public_ip' => 'nullable|ip',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_username' => VpsInstance::SSH_USERNAME_RULE,
            'root_password' => 'nullable|string|min:8',
            'app_url' => ['nullable', 'url:http,https', 'max:255'],
        ], self::PANEL_URL_MESSAGES + VpsInstance::SSH_USERNAME_MESSAGES);

        $existingOrder = Order::where('shopee_order_id', $validated['shopee_order_id'])->first();
        if ($existingOrder) {
            return back()->withInput()->withErrors([
                'shopee_order_id' => 'Shopee Order ID ini sudah pernah diproses sebelumnya (Order #' . $existingOrder->id . ').'
            ]);
        }

        $spec = VpsSpec::findOrFail($validated['vps_spec_id']);
        $gate = app(\App\Services\Provisioning\ProvisioningGate::class);
        $autoCapable = $spec->hasUpstreamMapping() && $gate->allows($spec);
        if (! empty($validated['shopee_mode'])) {
            $shopeeMode = $validated['shopee_mode'];
        } elseif ($request->boolean('auto_provision')) {
            $shopeeMode = 'manual';
        } else {
            $shopeeMode = $autoCapable ? 'auto' : 'manual';
        }

        if ($shopeeMode === 'manual' && $request->boolean('auto_provision') && empty($validated['public_ip'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'public_ip' => 'IP Publik wajib diisi saat alokasi manual langsung.',
            ]);
        }

        $provider = Order::DEFAULT_PROVIDER;
        $datacenterLocation = $validated['datacenter_location'] ?? 'indonesia';

        $generatedUsername = 'cv_' . Str::lower(Str::random(8));
        while (User::where('username', $generatedUsername)->exists()) {
            $generatedUsername = 'cv_' . Str::lower(Str::random(8));
        }

        $plainPassword = Str::password(16, true, true, false, false);

        $user = User::where('email', strtolower($validated['customer_email']))->first();
        if (!$user) {
            $user = User::create([
                'username' => $generatedUsername,
                'email' => strtolower($validated['customer_email']),
                'password' => Hash::make($plainPassword),
                'full_name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'] ?? null,
                'channel' => 'shopee',
                'shopee_order_id' => $validated['shopee_order_id'],
            ]);
            $isNewUser = true;
        } else {
            $isNewUser = false;
            if (empty($user->phone) && !empty($validated['customer_phone'])) {
                $user->update(['phone' => $validated['customer_phone']]);
            }
        }

        $organization = $user->currentOrganization
            ?? $user->organizations()->first()
            ?? $user->createPersonalOrganization();
        $user->switchToOrganization($organization);

        $cleanHostname = !empty($validated['hostname'])
            ? Str::slug($validated['hostname'])
            : ('vps-' . Str::slug($user->username));

        app(\App\Services\UpstreamQuotaService::class)->guardCheckout(
            $spec,
            $datacenterLocation,
            $user->id,
            canReject: false,
        );

        try {
            $result = DB::transaction(function () use ($user, $organization, $spec, $validated, $provider, $datacenterLocation, $cleanHostname, $shopeeMode) {
                $orderMeta = ['shopee_mode' => $shopeeMode];
                if ($shopeeMode === 'manual') {
                    $orderMeta['manual_fulfillment'] = true;
                }

                $order = Order::create([
                    'customer_id' => $user->id,
                    'organization_id' => $organization->id,
                    'vps_spec_id' => $spec->id,
                    'control_panel' => $validated['control_panel'],
                    'provider' => $provider,
                    'hostname' => $cleanHostname,
                    'root_password' => $validated['root_password'] ?? Str::password(16, true, true, false, false),
                    'datacenter_location' => $datacenterLocation,
                    'os' => $validated['os'],
                    'billing_cycle' => 'monthly',
                    'status' => 'pending',
                    'channel' => 'shopee',
                    'shopee_order_id' => $validated['shopee_order_id'],
                    'payment_method' => 'shopee_pay',
                    'amount' => $spec->sell_price,
                    'setup_fee' => 0,
                    'paid_at' => null,
                    'last_status_change_at' => now(),
                    'metadata' => $orderMeta,
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => 'pending',
                    'reason' => 'Order Shopee dibuat oleh Admin.',
                    'actor_user_id' => Auth::id(),
                    'actor_type' => 'admin',
                    'ip_address' => request()->ip(),
                    'metadata' => ['shopee_order_id' => $validated['shopee_order_id']],
                    'created_at' => now(),
                ]);

                $invoiceNumber = 'INV-SHP-' . date('Ym') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);
                $invoice = Invoice::create([
                    'order_id' => $order->id,
                    'invoice_number' => $invoiceNumber,
                    'amount' => $spec->sell_price,
                    'status' => 'sent',
                    'issued_at' => now(),
                    'due_at' => now()->addDays(30),
                    'paid_at' => null,
                ]);

                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'provider' => 'shopee',
                    'provider_transaction_id' => 'SHOPEE-' . $validated['shopee_order_id'],
                    'provider_order_ref' => $validated['shopee_order_id'],
                    'payment_method' => 'shopee_pay',
                    'amount' => $spec->sell_price,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'settled_at' => now(),
                    'raw_payload' => [
                        'shopee_order_id' => $validated['shopee_order_id'],
                        'admin_user_id' => Auth::id(),
                    ],
                ]);

                $this->orderStateMachine->transition($order, 'paid', [
                    'reason' => 'Order Shopee ditandai lunas.',
                    'actor_type' => 'admin',
                    'metadata' => ['transaction_id' => $tx->id],
                    'onLocked' => function (Order $locked) use ($tx, $invoice) {
                        $locked->paid_at = now();
                        $locked->save();
                        $invoice->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'paid_via_transaction_id' => $tx->id,
                        ]);
                    },
                ]);

                return $order->fresh();
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Gagal memproses order Shopee: ' . $e->getMessage());
        }

        $order = $result;
        $createdInstance = null;
        $defaultSshUser = VpsInstance::defaultSshUsernameForOs($validated['os']);

        if ($shopeeMode === 'manual' && ! empty($validated['auto_provision']) && ! empty($validated['public_ip'])) {
            try {
                $createdInstance = DB::transaction(function () use ($order, $user, $spec, $validated, $provider, $datacenterLocation, $cleanHostname, $defaultSshUser) {
                    $this->orderStateMachine->transition($order, 'provisioning', [
                        'reason' => 'Auto-provision dari Shopee flow.',
                        'actor_type' => 'admin',
                        'onLocked' => function (Order $locked) {
                            $locked->provisioning_attempts = ($locked->provisioning_attempts ?? 0) + 1;
                            $locked->save();
                        },
                    ]);
                    $order->refresh();

                    $startsAt = $order->provisioningStartsAt();
                    $expiresAt = $startsAt->copy()->addMonth();
                    $graceEndsAt = $expiresAt->copy()->addDays(app(\App\Services\RenewalService::class)->graceDaysForOrder($order));
                    $rootPassword = !empty($validated['root_password'])
                        ? $validated['root_password']
                        : Str::password(16, true, true, false, false);
                    $sshPort = !empty($validated['ssh_port']) ? (int)$validated['ssh_port'] : 22;

                    $hasPanel = $validated['control_panel'] !== 'none';
                    $appUrl = $hasPanel && !empty($validated['app_url']) ? $validated['app_url'] : null;
                    $osLabel = Order::osLabels()[$validated['os']] ?? $validated['os'];

                    $instance = VpsInstance::create([
                        'customer_id' => $user->id,
                        'order_id' => $order->id,
                        'organization_id' => $order->organization_id,
                        'hostname' => $cleanHostname,
                        'public_ip' => $validated['public_ip'],
                        'private_ip' => null,
                        'ssh_port' => $sshPort,
                        'ssh_username' => ! empty($validated['ssh_username'])
                            ? VpsInstance::normalizeSshUsername($validated['ssh_username'])
                            : $defaultSshUser,
                        'initial_root_password' => $rootPassword,
                        'os' => $osLabel,
                        'datacenter_location' => $datacenterLocation === 'singapore' ? 'SG-SIN01' : 'ID-CGK01',
                        'status' => 'provisioning',
                        'cpu' => $spec->cpu,
                        'ram' => $spec->ram,
                        'disk' => $spec->disk,
                        'control_panel' => $validated['control_panel'],
                        'provider' => $provider,
                        'app_url' => $appUrl,
                        'uptime_percent' => null,
                        'billing_cycle' => 'monthly',
                        'starts_at' => $startsAt,
                        'expires_at' => $expiresAt,
                        'grace_period_ends_at' => $graceEndsAt,
                        'auto_renew' => false,
                    ]);

                    VpsStatusHistory::create([
                        'vps_instance_id' => $instance->id,
                        'from_status' => null,
                        'to_status' => 'provisioning',
                        'reason' => 'Shopee auto-provision.',
                        'actor_user_id' => Auth::id(),
                        'actor_type' => 'admin',
                        'ip_address' => request()->ip(),
                        'metadata' => ['order_id' => $order->id],
                        'created_at' => now(),
                    ]);

                    $this->vpsStateMachine->transition($instance, 'running', [
                        'reason' => 'Auto-provision selesai.',
                        'actor_type' => 'admin',
                    ]);

                    $this->orderStateMachine->transition($order, 'active', [
                        'reason' => 'Auto-provision Shopee selesai.',
                        'actor_type' => 'admin',
                        'metadata' => ['vps_instance_id' => $instance->id],
                        'onLocked' => function (Order $locked) use ($startsAt, $expiresAt, $graceEndsAt) {
                            $locked->starts_at = $startsAt;
                            $locked->expires_at = $expiresAt;
                            $locked->grace_period_ends_at = $graceEndsAt;
                            $locked->save();
                        },
                    ]);

                    $instance->fresh()->logActivity(
                        action: 'provision',
                        description: "VPS auto-provisioning untuk Order Shopee {$validated['shopee_order_id']} oleh Administrator.",
                        status: 'completed',
                        userId: Auth::id()
                    );

                    app(\App\Services\SubscriptionSyncService::class)->ensureForInstance($instance->fresh());

                    return $instance;
                });
            } catch (\Throwable $e) {
                return back()->with('error', 'Order Shopee dibuat & dibayar, tetapi auto-provisioning gagal: ' . $e->getMessage());
            }
        }

        try {
            if ($isNewUser) {
                $user->notify(new \App\Notifications\WelcomeCredentialsNotification($plainPassword));
            }
            if (!empty($createdInstance)) {
                $user->notify(new \App\Notifications\VpsProvisionedNotification($createdInstance));
            } else {
                $order->loadMissing(['customer', 'vpsSpec', 'invoice']);
                $user->notify(new \App\Notifications\PaymentReceivedNotification($order, $order->invoice));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.shopee.notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        try {
            $adminEmails = \App\Support\MailIdentity::adminRecipients();
            $adminUser = User::whereIn('email', $adminEmails)->first() ?: User::where('is_admin', true)->first();
            if ($adminUser) {
                $order->loadMissing(['customer', 'vpsSpec']);
                $adminUser->notify(new \App\Notifications\AdminShopeeOrderNotification($order));
            }
            app(\App\Services\AdminAlertService::class)->alert(
                'payment.shopee_created',
                [
                    'title' => "Pesanan Shopee #{$order->id} ({$validated['shopee_order_id']})",
                    'order_id' => $order->id,
                    'send_email' => false,
                    'email_sent_override' => (bool) $adminUser,
                    'details' => [
                        'Order' => "#{$order->id} · {$validated['shopee_order_id']}",
                        'Paket' => $spec->name,
                        'Pelanggan' => ($user->full_name ?: $user->username) . " ({$user->email})",
                        'Status' => !empty($createdInstance) ? 'Instance Aktif (Manual Setup)' : 'Order Tercatat',
                    ],
                    'action_url' => url('/admin/orders?channel=shopee'),
                ],
                dedupeKey: 'shopee-' . $order->id,
                cooldownSeconds: 86400
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.shopee.admin_notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        $osDisplay = Order::osLabels()[$validated['os']] ?? $validated['os'];
        $stackDisplay = Order::stackLabels()[$validated['control_panel']] ?? strtoupper($validated['control_panel']);
        $providerDisplay = Order::customerProviderLabel($provider);
        $dcDisplay = ($datacenterLocation === 'singapore') ? 'Singapore (Tier-3 Gateway)' : 'Indonesia (Cyber Jakarta)';

        return back()->with('shopee_credentials', [
            'username' => $user->username,
            'password' => $isNewUser ? $plainPassword : '(Gunakan password akun CravaCloud lama Anda)',
            'is_new_user' => $isNewUser,
            'customer_name' => $user->full_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? null,
            'shopee_order_id' => $validated['shopee_order_id'],
            'package' => $spec->name,
            'spec_specs' => "{$spec->cpu} Core CPU / {$spec->ram} GB RAM / {$spec->disk} GB NVMe",
            'provider' => $providerDisplay,
            'datacenter' => $dcDisplay,
            'os' => $osDisplay,
            'control_panel' => $stackDisplay,
            'order_id' => $order->id,
            'login_url' => route('login'),
            'is_provisioned' => !empty($createdInstance),
            'hostname' => $createdInstance?->hostname ?? $cleanHostname,
            'public_ip' => $createdInstance?->public_ip,
            'ssh_port' => $createdInstance?->ssh_port ?? 22,
            'ssh_username' => $createdInstance?->ssh_user ?? $defaultSshUser,
            'root_password' => $createdInstance?->initial_root_password,
            'app_url' => $createdInstance?->app_url,
        ])->with('success', 'Order Shopee ' . $validated['shopee_order_id'] . ' berhasil diproses dan dicatat ke sistem.');
    }
}
