<?php

namespace App\Http\Controllers;

use App\Models\DatacenterRegion;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\VpsSpec;
use App\Models\WebhookEvent;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\Exceptions\PendingOrderLimitException;
use App\Services\Provisioning\Exceptions\QuotaExhaustedException;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\UpstreamQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(protected OrderStateMachine $orderStateMachine)
    {
    }

    public function checkout(Request $request, $spec_id = null)
    {
        // Harga modal disembunyikan: halaman ini mengirim data paket ke browser
        // lewat Js::from(), sehingga seluruh atribut dapat dibaca dari view-source.
        // Tidak disembunyikan di level model karena form edit paket admin membutuhkannya.
        $specs = VpsSpec::where('category', 'vps')
            ->orderBy('sell_price', 'asc')
            ->get()
            ->each->makeHidden(['cost_price']);
        $defaultSpec = $specs->firstWhere('is_active', true) ?? $specs->first();
        $hasExplicitSpec = !empty($spec_id) || $request->has('spec_id');
        $selectedSpecId = $spec_id ?? $request->query('spec_id', $defaultSpec->id ?? 1);
        $selectedSpec = $specs->firstWhere('id', $selectedSpecId) ?? $defaultSpec;
        $stackRegistry = app(StackRegistry::class);
        $stackSupportedOs = $stackRegistry->supportedOsMap();
        $stackCatalog = $stackRegistry->uiCatalog(checkoutOnly: true);
        $stackLabels = Order::stackLabels();

        return view('order.checkout', compact(
            'specs',
            'selectedSpec',
            'hasExplicitSpec',
            'stackSupportedOs',
            'stackCatalog',
            'stackLabels'
        ));
    }

    public function applyCoupon(Request $request)
    {
        $validated = $request->validate([
            'spec_id' => 'required|exists:vps_specs,id',
            'coupon_code' => 'nullable|string|max:50',
            'apply_credit' => 'nullable|boolean',
        ]);

        $spec = VpsSpec::findOrFail($validated['spec_id']);
        $user = Auth::user();
        $couponCode = trim((string) ($validated['coupon_code'] ?? '')) ?: null;
        $applyCredit = $user !== null && $request->boolean('apply_credit');
        $taxRate = \App\Models\TaxRate::defaultForCountry($user?->country ?? 'ID');

        $breakdown = app(\App\Services\BillingService::class)->calculatePrice(
            $spec,
            'monthly',
            $couponCode,
            $applyCredit,
            $user,
            $taxRate
        );

        // Normalize legacy discount keys for checkout preview compatibility
        $breakdown['discount'] = $breakdown['coupon_discount'] ?? 0.0;

        $couponError = $breakdown['coupon']['error'] ?? null;

        return response()->json([
            'ok' => $couponError === null,
            'error' => $couponError,
            'breakdown' => $breakdown,
        ], $couponError && $couponCode !== null ? 422 : 200);
    }

    public function quickLogin(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim((string) $request->input('login'));
        $normalizedLogin = strtolower(preg_replace('/@([a-zA-Z0-9.-]+),([a-zA-Z]{2,})/i', '@$1.$2', $login));
        $user = User::whereRaw('LOWER(email) = ?', [$normalizedLogin])
            ->orWhereRaw('LOWER(username) = ?', [strtolower($login)])
            ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/username atau password tidak sesuai.',
            ], 422);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa.pending_user_id', $user->id);
            $request->session()->put('2fa.remember', true);
            return response()->json([
                'success' => false,
                'requires_2fa' => true,
                'redirect' => route('two-factor.challenge'),
                'message' => 'Autentikasi dua faktor diperlukan.',
            ], 200);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('2fa.passed', true);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'csrf_token' => csrf_token(),
            'message' => 'Login berhasil.',
        ]);
    }

    public function store(Request $request)
    {
        $stackRegistry = app(StackRegistry::class);

        $wasAuthenticated = Auth::check();

        $validated = $request->validate([
            'vps_spec_id' => 'required|exists:vps_specs,id',
            'control_panel' => ['required', 'string', Rule::in($stackRegistry->checkoutKeys(includeManagedDb: false))],
            'provider' => 'nullable|string|max:50',
            'datacenter_location' => 'required|in:singapore,indonesia',
            'os' => 'required|string',
            'billing_cycle' => ['nullable', 'string', Rule::in(['monthly'])],
            'coupon_code' => 'nullable|string|max:50',
            'apply_credit' => 'nullable|boolean',
            'hostname' => ['required', 'string', 'max:63', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'root_password' => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:online_payment,midtrans_snap,qris,bca_va,mandiri_va,bni_va,bri_va,cimb_va,permata_va,other_va,bsi_va,danamon_va,seabank_va,credit_card,gopay,shopeepay,ovo,dana,indomaret,akulaku',
            'warning_acknowledged' => 'nullable|boolean',
            'license_acknowledged' => 'nullable|boolean',
            // Guest fields if not logged in
            'full_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|min:3|max:50|alpha_dash|unique:users,username',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:25',
            'password' => 'nullable|string|min:8',
            'redirect_to_dashboard' => 'nullable',
            // PHASE 2 - persetujuan ketentuan bersifat WAJIB (clickwrap).
            // Divalidasi di sisi server agar tidak dapat dilewati dari peramban.
            'terms_accepted' => 'accepted',
        ], [
            'terms_accepted.accepted' => 'Anda wajib menyetujui Ketentuan Layanan dan Ketentuan Penggunaan sebelum melanjutkan pemesanan.',
        ]);

        // PHASE 4 - metode pembayaran wajib milik gateway yang aktif. UI checkout
        // sudah mengunci metode nonaktif, pemeriksaan ini menutup jalur kirim langsung.
        if (!PaymentGateway::isMethodActive($validated['payment_method'])) {
            $methodMessage = 'Metode pembayaran ini sedang tidak tersedia. Silakan pilih metode lain.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $methodMessage,
                    'errors' => ['payment_method' => [$methodMessage]],
                ], 422);
            }
            return back()->withInput()->withErrors(['payment_method' => $methodMessage]);
        }

        $spec = VpsSpec::findOrFail($validated['vps_spec_id']);
        if ($spec->category !== 'vps') {
            $retiredMessage = 'Paket layanan ini sudah tidak tersedia. Silakan pilih paket Cloud VPS yang aktif.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $retiredMessage,
                    'errors' => ['vps_spec_id' => [$retiredMessage]],
                ], 422);
            }
            return back()->withInput()->withErrors(['vps_spec_id' => $retiredMessage]);
        }

        if (!$spec->is_active) {
            $inactiveMessage = 'Paket layanan ini sedang dinonaktifkan sementara oleh tim CravaCloud untuk pemeliharaan sistem.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $inactiveMessage,
                    'errors' => ['vps_spec_id' => [$inactiveMessage]],
                ], 422);
            }
            return back()->withInput()->withErrors(['vps_spec_id' => $inactiveMessage]);
        }

        $validated['provider'] = Order::DEFAULT_PROVIDER;
        validator($validated, [
            'datacenter_location' => [Rule::in(['indonesia', 'singapore'])],
            'os' => [Rule::in(array_keys(Order::checkoutOperatingSystems()))],
        ])->validate();

        if (! $stackRegistry->supportsOs($validated['os'], $validated['control_panel'])) {
            $compatMessage = 'Kombinasi sistem operasi dan stack tersebut belum didukung. Silakan pilih OS yang kompatibel atau Tanpa Control Panel.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $compatMessage,
                    'errors' => ['os' => [$compatMessage]],
                ], 422);
            }
            return back()->withInput()->withErrors(['os' => $compatMessage]);
        }

        $stackDef = $stackRegistry->get($validated['control_panel']);
        if ($stackDef !== null) {
            if ($stackDef->hardBlockOnRam && (int) $spec->ram < $stackDef->minRamGb) {
                throw ValidationException::withMessages([
                    'control_panel' => "Stack {$stackDef->label} membutuhkan minimal {$stackDef->minRamGb} GB RAM. Paket {$spec->name} hanya memiliki {$spec->ram} GB RAM.",
                ]);
            }

            if ($stackDef->requiresLicense && ! $request->boolean('license_acknowledged')) {
                throw ValidationException::withMessages([
                    'license_acknowledged' => "Panel {$stackDef->label} tidak termasuk lisensi berbayar (BYOL). Harap konfirmasi pemahaman Anda sebelum melanjutkan.",
                ]);
            }

            if ($stackDef->needsWarningConfirmation() && ! $request->boolean('warning_acknowledged')) {
                throw ValidationException::withMessages([
                    'warning_acknowledged' => "Harap baca dan konfirmasi informasi penting untuk stack {$stackDef->label} sebelum melanjutkan.",
                ]);
            }
        }

        $user = Auth::user();

        // If not logged in, register or authenticate
        if (!$user) {
            $request->validate([
                'full_name' => 'required|string|max:255',
                'username' => 'nullable|string|min:3|max:50|alpha_dash|unique:users,username',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:25',
                'password' => 'required|string|min:8',
            ]);

            $cleanEmail = strtolower(trim((string)$validated['email']));
            $cleanEmail = preg_replace('/@([a-zA-Z0-9.-]+),([a-zA-Z]{2,})/i', '@$1.$2', $cleanEmail);

            $existingUser = User::whereRaw('LOWER(email) = ?', [$cleanEmail])->first();
            if ($existingUser) {
                if (!Hash::check($validated['password'], $existingUser->password)) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'Email ini sudah terdaftar. Silakan pilih tab "Sudah Punya Akun" untuk login atau masukkan password yang sesuai.',
                            'errors' => [
                                'email' => ['Email ini sudah terdaftar. Silakan login atau gunakan password yang sesuai.']
                            ]
                        ], 422);
                    }
                    return back()->withInput()->withErrors([
                        'email' => 'Email ini sudah terdaftar. Silakan login terlebih dahulu atau gunakan password yang benar.',
                    ]);
                }

                // Wajib hormati 2FA bila akun eksisting mengaktifkannya (tutup celah 2FA bypass via guest checkout).
                if ($existingUser->hasTwoFactorEnabled()) {
                    $request->session()->put('2fa.pending_user_id', $existingUser->id);
                    $request->session()->put('2fa.remember', true);

                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'requires_2fa' => true,
                            'redirect' => route('two-factor.challenge'),
                            'message' => 'Akun ini mengaktifkan autentikasi dua faktor (2FA). Silakan selesaikan verifikasi 2FA terlebih dahulu.',
                        ], 422);
                    }

                    return redirect()->route('two-factor.challenge')
                        ->with('info', 'Akun ini mengaktifkan autentikasi dua faktor (2FA). Silakan masukkan kode verifikasi terlebih dahulu.');
                }

                $user = $existingUser;
            } else {
                $requestedUsername = $request->input('username');
                if (!empty($requestedUsername)) {
                    $generatedUsername = strtolower(trim($requestedUsername));
                } else {
                    $generatedUsername = 'cv_' . Str::lower(Str::random(7));
                    while (User::where('username', $generatedUsername)->exists()) {
                        $generatedUsername = 'cv_' . Str::lower(Str::random(7));
                    }
                }

                $user = User::create([
                    'full_name' => $validated['full_name'],
                    'username' => $generatedUsername,
                    'email' => $cleanEmail,
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'channel' => 'website',
                ]);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('2fa.passed', true);
        } else {
            // Update phone if previously empty
            if (!empty($validated['phone']) && empty($user->phone)) {
                $user->update(['phone' => $validated['phone']]);
            }
        }

        // Checkout is public (guest route), so ensure a tenant context exists
        // before creating any billable resource.
        $organization = $user->currentOrganization;
        if (!$organization) {
            $organization = $user->organizations()->first() ?? $user->createPersonalOrganization();
            $user->switchToOrganization($organization);
        }

        if ($organization->quota && !$organization->quota->canAddVps()) {
            return back()->withInput()->with('error', 'Kuota VPS organisasi sudah penuh. Upgrade kuota atau pilih organisasi lain.');
        }

        $spec = VpsSpec::findOrFail($validated['vps_spec_id']);

        // Tencent integration Phase 2 — quota guard (revision 1 anti-DoS).
        // Default mode 'observe': log tanpa menolak. Mode 'enforce' (setting
        // quota_guard_mode): tolak bila pool penuh / >2 order pending per user,
        // dengan pesan netral + pilihan lokasi alternatif (D4, white-label).
        try {
            app(UpstreamQuotaService::class)->guardCheckout(
                $spec,
                $validated['datacenter_location'],
                $user->id,
            );
        } catch (QuotaExhaustedException|PendingOrderLimitException $e) {
            $guardMessage = $e->customerMessage();
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $guardMessage,
                    'errors' => ['vps_spec_id' => [$guardMessage]],
                ], 422);
            }
            return back()->withInput()->withErrors(['vps_spec_id' => $guardMessage]);
        }

        $cycle = 'monthly';
        $billing = app(\App\Services\BillingService::class);
        $couponCode = trim((string) ($validated['coupon_code'] ?? '')) ?: null;
        $useCredit = $wasAuthenticated && $request->boolean('apply_credit');
        $taxRate = \App\Models\TaxRate::defaultForCountry($user->country ?? 'ID');
        $breakdown = $billing->calculatePrice($spec, $cycle, $couponCode, $useCredit, $user, $taxRate);

        if ($couponCode !== null && ! empty($breakdown['coupon']['error'])) {
            $couponMsg = (string) $breakdown['coupon']['error'];
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $couponMsg,
                    'errors' => ['coupon_code' => [$couponMsg]],
                ], 422);
            }

            return back()->withInput()->withErrors(['coupon_code' => $couponMsg]);
        }

        $netAmount = (float) $breakdown['total'];
        $setupFee = 0;

        // Region upstream untuk order ini (nullable bila lokasi belum dipetakan).
        $regionCode = UpstreamQuotaService::LOCATION_REGION[$validated['datacenter_location']] ?? null;
        $datacenterRegion = $regionCode ? DatacenterRegion::where('code', $regionCode)->first() : null;

        // PHASE 2 - bukti persetujuan dicatat bersama order dalam satu transaksi.
        $termsVersion = config('legal.terms_version');
        $termsIp = $request->ip();
        $termsAgent = $request->userAgent();

        $orderMetadata = null;
        if ($stackDef !== null && ($stackDef->needsWarningConfirmation() || $request->boolean('warning_acknowledged') || $request->boolean('license_acknowledged'))) {
            $orderMetadata = [
                'warning_acknowledged' => $request->boolean('warning_acknowledged'),
                'license_acknowledged' => $stackDef->requiresLicense ? $request->boolean('license_acknowledged') : false,
                'acknowledged_stack' => $stackDef->key,
                'acknowledged_at' => now()->toIso8601String(),
            ];
        }

        // Bungkus dalam transaction agar order + invoice + status history atomic.
        $order = DB::transaction(function () use (
            $user, $organization, $spec, $validated, $cycle, $netAmount, $setupFee,
            $termsVersion, $termsIp, $termsAgent, $datacenterRegion, $orderMetadata,
            $billing, $breakdown
        ) {
            $order = Order::create([
                'customer_id' => $user->id,
                'organization_id' => $organization->id,
                'vps_spec_id' => $spec->id,
                'control_panel' => $validated['control_panel'],
                'provider' => $validated['provider'],
                'hostname' => strtolower($validated['hostname']),
                'root_password' => $validated['root_password'] ?? null,
                'datacenter_location' => $validated['datacenter_location'],
                'datacenter_region_id' => $datacenterRegion?->id,
                'os' => $validated['os'],
                'billing_cycle' => $cycle,
                'status' => 'pending',
                'channel' => 'website',
                'payment_method' => $netAmount <= 0
                    ? (($breakdown['credit_applied'] ?? 0) > 0 ? 'credit_balance' : 'coupon')
                    : $validated['payment_method'],
                'amount' => $netAmount,
                'setup_fee' => $setupFee,
                'paid_at' => null,
                'starts_at' => null,
                'expires_at' => null,
                'last_status_change_at' => now(),
                'terms_version' => $termsVersion,
                'metadata' => $orderMetadata,
            ]);

            // Baris ini adalah alat bukti. Tanpa versi yang tersimpan, kita tidak
            // dapat membuktikan naskah mana yang disetujui bila isinya berubah.
            TermsAcceptance::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'terms_version' => $termsVersion,
                'accepted_at' => now(),
                'ip_address' => $termsIp,
                'user_agent' => $termsAgent,
            ]);

            $invoice = $billing->generateInvoice($order, $breakdown);

            // Catat entry awal di history (from_status = null berarti "created").
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'reason' => 'Order dibuat, menunggu pembayaran.',
                'actor_user_id' => $user->id,
                'actor_type' => 'customer',
                'ip_address' => request()->ip(),
                'metadata' => [
                    'amount' => $netAmount,
                    'payment_method' => $order->payment_method,
                    'billing_cycle' => $cycle,
                    'coupon_discount' => $breakdown['coupon_discount'] ?? 0,
                    'credit_applied' => $breakdown['credit_applied'] ?? 0,
                ],
                'created_at' => now(),
            ]);

            // Jika total menjadi Rp0 (tertutup kupon 100% / saldo credit), langsung lunasi via StateMachine.
            if ($netAmount <= 0.009) {
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'provider' => 'internal',
                    'provider_transaction_id' => 'INT-' . strtoupper(Str::random(12)),
                    'provider_order_ref' => 'CRV-' . $order->id,
                    'payment_method' => $order->payment_method,
                    'amount' => 0,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'settled_at' => now(),
                ]);

                $this->orderStateMachine->transition($order, 'paid', [
                    'reason' => 'Order lunas via kupon/saldo kredit tanpa gateway.',
                    'actor_user_id' => $user->id,
                    'actor_type' => 'customer',
                    'metadata' => ['transaction_id' => $tx->id],
                    'onLocked' => function (Order $locked) use ($invoice, $tx) {
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
            }

            return $order;
        });

        // Simpan ID order aktif di session browser agar bisa dipulihkan saat kembali dari Midtrans
        session(['last_order_id' => $order->id]);

        if ($order->paid_at) {
            $successUrl = route('order.success', $order->id);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'order_id' => $order->id,
                    'redirect_url' => $successUrl,
                ]);
            }

            return redirect($successUrl)->with('success', 'Pesanan berhasil dilunasi menggunakan saldo kredit / kupon promo.');
        }

        // VPS Instance TIDAK dibuat di sini.
        // Instance hanya dibuat oleh Admin/Orchestrator setelah payment terkonfirmasi via webhook.

        $snapToken = null;
        $snapRedirectUrl = null;

        // Dispatch ke Midtrans Snap (gateway tunggal)
        if (PaymentGateway::isMidtransMethod($order->payment_method)) {
            $snapResult = \App\Services\Payments\MidtransService::createSnapTransaction($order);
            if (!empty($snapResult['success'])) {
                $snapToken = $snapResult['token'] ?? null;
                $snapRedirectUrl = $snapResult['redirect_url'] ?? null;
            }
        }

        $redirectUrl = $snapRedirectUrl ?: route('order.payment', $order->id);

        // If user explicitly chose to postpone payment and view dashboard (locked state)
        if ($request->filled('redirect_to_dashboard') && $request->input('redirect_to_dashboard') == '1') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'order_id' => $order->id,
                    'redirect_url' => route('dashboard.index'),
                ]);
            }

            return redirect()->route('dashboard.index')
                ->with('warning', 'Pesanan VPS Anda telah tersimpan! Layanan saat ini TERKUNCI menunggu pembayaran. Silakan selesaikan pembayaran untuk mengaktifkan.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'snap_token' => $snapToken,
                'snap_redirect_url' => $snapRedirectUrl,
                'redirect_url' => $redirectUrl,
            ]);
        }

        // Jika ada snap redirect url, alihkan langsung ke halaman pembayaran Midtrans
        if ($snapRedirectUrl) {
            return redirect()->away($snapRedirectUrl);
        }

        return redirect()->route('order.payment', $order->id)
            ->with('info', 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran melalui Midtrans.');
    }

    public function payment($id)
    {
        $order = Order::with(['customer', 'vpsSpec', 'invoice'])->findOrFail($id);

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Cek sinkronisasi real-time ke Midtrans jika order masih pending
        if (!$order->paid_at) {
            $revalidateResponse = $this->revalidatePendingOrder($order);
            if ($revalidateResponse) {
                return $revalidateResponse;
            }

            \App\Services\Payments\MidtransService::checkAndSyncStatus($order);
            $order->refresh();
        }

        // If order already paid, redirect directly to success callback view
        if ($order->paid_at) {
            return redirect()->route('order.success', $order->id);
        }

        // Untuk metode pembayaran Midtrans eksplisit, alihkan ke halaman status pembayaran
        if (PaymentGateway::isMidtransMethod($order->payment_method)) {
            return redirect()->route('order.payment.status', $order->id);
        }

        $devSimulateEnabled = config('app.env') === 'local' && (bool) config('cravacloud.dev_simulate_payment', false);
        $elapsedSeconds = (int) max(0, now()->diffInSeconds($order->created_at));

        // Integrasi Midtrans Snap (termasuk fallback order tanpa payment_method spesifik)
        $isMidtrans = true;
        $snapToken = null;
        $snapRedirectUrl = null;
        $snapJsUrl = null;
        $snapClientKey = null;
        $snapError = null;

        $snapResult = \App\Services\Payments\MidtransService::createSnapTransaction($order);
        if ($snapResult['success']) {
            $snapToken = $snapResult['token'];
            $snapRedirectUrl = $snapResult['redirect_url'];
        } else {
            $snapError = $snapResult['error'] ?? 'Gagal membuat sesi pembayaran Midtrans.';
        }
        $snapJsUrl = \App\Services\Payments\MidtransService::getSnapJsUrl();
        $snapClientKey = \App\Services\Payments\MidtransService::getClientKey();

        return view('order.payment-gateway', compact(
            'order',
            'devSimulateEnabled',
            'elapsedSeconds',
            'isMidtrans',
            'snapToken',
            'snapRedirectUrl',
            'snapJsUrl',
            'snapClientKey',
            'snapError'
        ));
    }

    /**
     * Halaman "menunggu konfirmasi pembayaran" — read-only.
     * Frontend polling paymentStatusJson secara berkala; ketika paid_at terisi
     * (oleh webhook atau sync otomatis) baru redirect ke success page.
     */
    public function paymentStatus($id)
    {
        $order = Order::with(['customer', 'vpsSpec', 'invoice'])->findOrFail($id);

        if (!Auth::check() && session('last_order_id') == $order->id && $order->customer_id) {
            Auth::loginUsingId($order->customer_id);
        }

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Cek sinkronisasi real-time ke Midtrans jika masih pending
        if (!$order->paid_at) {
            $revalidateResponse = $this->revalidatePendingOrder($order);
            if ($revalidateResponse) {
                return $revalidateResponse;
            }

            if (PaymentGateway::isMidtransMethod($order->payment_method)) {
                \App\Services\Payments\MidtransService::checkAndSyncStatus($order);
            }
            $order->refresh();
        }

        if ($order->paid_at) {
            return redirect()->route('order.success', $order->id);
        }

        $midtransTx = \App\Models\PaymentTransaction::where('order_id', $order->id)
            ->where('provider', 'midtrans')
            ->latest()
            ->first();
        $snapToken = $midtransTx?->raw_payload['snap_token'] ?? null;
        $snapRedirectUrl = $midtransTx?->raw_payload['snap_redirect_url'] ?? null;

        if (PaymentGateway::isMidtransMethod($order->payment_method) && empty($snapToken)) {
            $snapResult = \App\Services\Payments\MidtransService::createSnapTransaction($order);
            if (!empty($snapResult['success'])) {
                $snapToken = $snapResult['token'] ?? null;
                $snapRedirectUrl = $snapResult['redirect_url'] ?? null;
            }
        }

        $snapJsUrl = \App\Services\Payments\MidtransService::getSnapJsUrl();
        $snapClientKey = \App\Services\Payments\MidtransService::getClientKey();

        return view('order.payment-pending', compact(
            'order',
            'snapToken',
            'snapRedirectUrl',
            'snapJsUrl',
            'snapClientKey'
        ));
    }

    /**
     * JSON endpoint untuk polling status.
     * Secara otomatis menyinkronkan status dengan Core API Midtrans jika belum lunas.
     */
    public function paymentStatusJson($id)
    {
        $order = Order::with('invoice')->findOrFail($id);

        if (!Auth::check() && session('last_order_id') == $order->id && $order->customer_id) {
            Auth::loginUsingId($order->customer_id);
        }

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Status dibaca dari DB saja — sinkronisasi Midtrans dilakukan via
        // webhook dan scheduled command (CheckMidtransStatusCommand).
        // JANGAN panggil MidtransService::checkAndSyncStatus() di sini
        // karena endpoint ini di-poll frontend setiap beberapa detik.

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'paid_at' => optional($order->paid_at)?->toIso8601String(),
            'invoice_status' => $order->invoice?->status,
            'redirect_to' => $order->paid_at ? route('order.success', $order->id) : null,
        ]);
    }

    /**
     * DEV ONLY: simulasi payment untuk testing lokal tanpa gateway asli.
     * Ini menggantikan lubang keamanan `completePayment` yang lama.
     *
     * Endpoint ini:
     *   1. Hanya aktif di APP_ENV=local + env APP_DEV_SIMULATE_PAYMENT=true.
     *   2. Hanya bisa dipanggil oleh admin ATAU pemilik order.
     *   3. Rate-limited (5/min).
     *   4. Membuat payment_transactions record + transisi order via state machine.
     *   5. Ditandai jelas sebagai "dev simulation" di metadata & log.
     *
     * Di production, endpoint ini otomatis abort(403) karena guard di atas.
     */
    public function devSimulatePayment(Request $request, $id)
    {
        // Guard 1: environment
        if (!app()->environment('local')) {
            abort(404, 'Endpoint ini hanya tersedia di environment lokal.');
        }
        // Guard 2: feature flag
        if (!config('cravacloud.dev_simulate_payment', false)) {
            abort(404, 'Simulasi pembayaran dinonaktifkan. Set APP_DEV_SIMULATE_PAYMENT=true di .env untuk mengaktifkan.');
        }

        $order = Order::with('invoice')->findOrFail($id);

        // Guard 3: hanya pemilik order atau admin
        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        if ($order->paid_at) {
            return redirect()->route('order.success', $order->id)
                ->with('info', 'Order ini sudah tercatat lunas sebelumnya.');
        }

        try {
            DB::transaction(function () use ($order) {
                // Catat transaksi simulasi.
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice?->id,
                    'provider' => 'dev_simulator',
                    'provider_transaction_id' => 'DEV-' . Str::upper(Str::random(12)),
                    'provider_order_ref' => 'ORDER-' . $order->id,
                    'payment_method' => $order->payment_method,
                    'amount' => $order->amount,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'raw_payload' => [
                        'note' => 'Dev simulation, not a real payment.',
                        'triggered_by_user_id' => Auth::id(),
                        'ip' => request()->ip(),
                    ],
                    'settled_at' => now(),
                ]);

                // Transisi order pending → paid via state machine.
                $this->orderStateMachine->transition(
                    $order,
                    'paid',
                    [
                        'reason' => 'Dev simulator marked payment settled.',
                        'actor_type' => 'system',
                        'metadata' => [
                            'source' => 'dev_simulator',
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

            Log::warning('order.payment.dev_simulated', [
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            // Notifikasi pelanggan & admin dikirim otomatis oleh PaymentCompletionService
            // (dipicu OrderStateMachine saat transisi ke 'paid').
        } catch (\Throwable $e) {
            Log::error('order.payment.dev_simulate_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Simulasi pembayaran gagal: ' . $e->getMessage());
        }

        return redirect()->route('order.success', $order->id)
            ->with('success', '[DEV] Pembayaran disimulasi. Order menunggu provisioning oleh admin.');
    }

    public function success($id)
    {
        $order = Order::with(['customer', 'vpsSpec', 'invoice', 'vpsInstance'])->findOrFail($id);

        if (!Auth::check() && session('last_order_id') == $order->id && $order->customer_id) {
            Auth::loginUsingId($order->customer_id);
        }

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Cek sinkronisasi real-time ke Midtrans jika belum tercatat paid_at
        if (!$order->paid_at && PaymentGateway::isMidtransMethod($order->payment_method)) {
            \App\Services\Payments\MidtransService::checkAndSyncStatus($order);
            $order->refresh();
        }

        // Jika pesanan belum lunas dan bukan admin, arahkan ke halaman status pembayaran
        if (!$order->paid_at && !in_array($order->status, ['paid', 'provisioning', 'active'], true) && !Auth::user()->is_admin) {
            return redirect()->route('order.payment.status', $order->id);
        }

        return view('order.success', compact('order'));
    }

    /**
     * Callback handler setelah pembayaran dari payment gateway (Midtrans).
     */
    public function paymentCallback(Request $request)
    {
        $orderId = $request->query('order_id')
            ?? $request->query('refId')
            ?? $request->query('ref_id')
            ?? $request->query('reference')
            ?? $request->query('transaction_id')
            ?? $request->query('order_number')
            ?? session('last_order_id');

        $user = Auth::user();
        $order = null;

        if ($orderId) {
            // 1. Cek numeric primary key (orders.id)
            if (is_numeric($orderId)) {
                $order = Order::find($orderId);
            }

            // 1b. Cek prefix order_id Midtrans (CRV-123-ts / ORDER-123-ts)
            if (!$order && preg_match('/^(?:CRV|ORDER|VX)[-_]?(\d+)(?:[-_].*)?$/i', (string) $orderId, $m)) {
                $order = Order::find((int) $m[1]);
            }

            // 2. Cek Shopee order ID
            if (!$order) {
                $order = Order::where('shopee_order_id', $orderId)->first();
            }

            // 3. Cek via PaymentTransaction
            if (!$order) {
                $tx = PaymentTransaction::where('provider_transaction_id', $orderId)
                    ->orWhere('provider_order_ref', $orderId)
                    ->latest()
                    ->first();
                if ($tx && $tx->order_id) {
                    $order = Order::find($tx->order_id);
                }
            }

            // 4. Cek via WebhookEvent
            if (!$order) {
                $evt = WebhookEvent::where('event_id', $orderId)
                    ->whereNotNull('order_id')
                    ->latest()
                    ->first();
                if ($evt && $evt->order_id) {
                    $order = Order::find($evt->order_id);
                }
            }
        }

        // 5. Fallback pencarian order terbaru user yang sedang login atau session
        if (!$order && $user) {
            $order = Order::where('customer_id', $user->id)->latest()->first();
        }

        if (!$order && session('last_order_id')) {
            $order = Order::find(session('last_order_id'));
        }

        // 6. Pemulihan Sesi Pengguna secara aman:
        // Jika user belum login di browser ini tapi browser memiliki session order yang cocok,
        // login-kan kembali agar user tidak terlempar ke form login kosong
        if (!$user && $order && $order->customer_id) {
            if (session('last_order_id') == $order->id) {
                Auth::loginUsingId($order->customer_id);
                $user = Auth::user();
            }
        }

        // 7. Penanganan Redirect:
        // A. Pesanan sudah lunas / diproses
        if ($order && in_array($order->status, ['paid', 'provisioning', 'active'], true) && $order->paid_at) {
            if ($user) {
                return redirect()->route('dashboard.index', [
                    'payment_success' => 1,
                    'order_id' => $order->id,
                ])->with('payment_success_order_id', $order->id);
            }

            return redirect()->route('order.success', $order->id);
        }

        // B. Pesanan masih pending (webhook sedang berjalan / menunggu pembayaran)
        if ($order && $order->status === 'pending') {
            return redirect()->route('order.payment.status', $order->id)
                ->with('info', 'Pembayaran sedang diproses atau menunggu verifikasi sistem. Silakan pantau status pembayaran Anda.');
        }

        // C. User terotentikasi tapi order tidak spesifik
        if ($user) {
            return redirect()->route('dashboard.index')
                ->with('info', 'Selamat datang di Dashboard. Pantau status pesanan dan layanan server Anda di sini.');
        }

        return redirect()->route('login')
            ->with('info', 'Silakan masuk ke akun CravaCloud Anda untuk memantau status pesanan.');
    }

    /**
     * PILAR 3 - Price & Availability Re-Validation untuk order yang masih pending.
     *
     * Mengecek apakah:
     * 1. Order telah kedaluwarsa (> 24 jam belum dibayar).
     * 2. Paket dinonaktifkan di supplier / katalog (is_active / is_renewable).
     * 3. Harga katalog atau modal naik/berubah sejak order dibuat.
     */
    protected function revalidatePendingOrder(Order $order): ?\Illuminate\Http\RedirectResponse
    {
        if ($order->paid_at || $order->status !== 'pending') {
            return null;
        }

        // Cek 1: TTL 24 Jam
        if ($order->created_at && $order->created_at->lt(now()->subHours(24))) {
            try {
                $this->orderStateMachine->transition($order, 'expired', [
                    'reason' => 'Batas waktu pembayaran 24 jam telah berakhir.',
                    'actor_type' => 'system',
                ]);
            } catch (\Throwable $e) {
                $order->update(['status' => 'expired']);
            }
            return redirect()->route('dashboard.index')
                ->with('error', "Pesanan #{$order->id} telah kedaluwarsa karena tidak diselesaikan dalam 24 jam.");
        }

        // Cek 2: Ketersediaan & Siklus Produk
        if ($order->isRenewal()) {
            $instance = $order->resolved_instance ?? ($order->vps_instance_id ? \App\Models\VpsInstance::find($order->vps_instance_id) : $order->vpsInstance);
            $spec = $order->vpsSpec;

            if ($spec && !$spec->is_renewable) {
                // Paket lama End-of-Life
                if ($spec->replacement_spec_id) {
                    $replacement = $spec->replacementSpec ?? \App\Models\VpsSpec::find($spec->replacement_spec_id);
                    if ($replacement) {
                        if ($order->vps_spec_id !== $replacement->id) {
                            $order->vps_spec_id = $replacement->id;
                            $order->save();
                        }
                    }
                } else {
                    // EOL permanen tanpa pengganti: batalkan order
                    try {
                        $this->orderStateMachine->transition($order, 'cancelled', [
                            'reason' => 'Paket VPS telah dihentikan secara permanen (End-of-Life) oleh penyedia upstream.',
                            'actor_type' => 'system',
                        ]);
                    } catch (\Throwable $e) {
                        $order->update(['status' => 'cancelled']);
                    }

                    $targetRedirect = $instance ? route('dashboard.vps.show', $instance->id) : route('dashboard.billing');
                    return redirect($targetRedirect)->with('error', 'Maaf, paket VPS untuk server ini telah dihentikan (End-of-Life) oleh penyedia infrastruktur dan tidak dapat diperpanjang lagi. Silakan hubungi tim dukungan kami untuk proses migrasi.');
                }
            }

            // Hitung harga perpanjangan yang diharapkan saat ini
            $expectedPrice = (float) ($instance ? $instance->renewal_price : ($spec ? $spec->effectiveRenewalPrice() : $order->amount));
            if ($expectedPrice > 0 && abs((float) $order->amount - $expectedPrice) > 0.01) {
                $order->amount = $expectedPrice;
                $order->save();

                if ($order->invoice) {
                    $order->invoice->update([
                        'amount' => $expectedPrice,
                        'subtotal' => $expectedPrice,
                        'total' => $expectedPrice,
                    ]);
                }

                // Invalidate transaksi/token pending lama
                PaymentTransaction::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->delete();

                session()->flash('warning', 'Tarif perpanjangan telah disesuaikan dengan skema katalog terbaru (Rp ' . number_format($expectedPrice, 0, ',', '.') . '). Silakan lanjutkan pembayaran dengan nominal terbaru.');
            }
        } else {
            // Order Baru
            $spec = $order->vpsSpec;
            if (!$spec || !$spec->is_active) {
                try {
                    $this->orderStateMachine->transition($order, 'cancelled', [
                        'reason' => 'Paket VPS tidak lagi aktif untuk pendaftaran baru.',
                        'actor_type' => 'system',
                    ]);
                } catch (\Throwable $e) {
                    $order->update(['status' => 'cancelled']);
                }

                return redirect()->route('order.checkout')
                    ->with('error', 'Maaf, ketersediaan paket pada pesanan yang belum dibayar ini telah ditutup oleh penyedia infrastruktur. Pesanan dibatalkan otomatis.');
            }

            $expectedPrice = (float) $spec->sell_price;
            if (abs((float) $order->amount - $expectedPrice) > 0.01) {
                $order->amount = $expectedPrice;
                $order->save();

                if ($order->invoice) {
                    $order->invoice->update([
                        'amount' => $expectedPrice,
                        'subtotal' => $expectedPrice,
                        'total' => $expectedPrice,
                    ]);
                }

                PaymentTransaction::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->delete();

                session()->flash('warning', 'Penyesuaian tarif infrastruktur: nominal tagihan telah disesuaikan ke harga terbaru Rp ' . number_format($expectedPrice, 0, ',', '.') . '.');
            }
        }

        return null;
    }
}
