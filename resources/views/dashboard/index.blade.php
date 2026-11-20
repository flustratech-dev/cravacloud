@extends('layouts.dashboard', ['title' => 'Dashboard', 'headerTitle' => 'VPS Instances Saya'])

@section('content')
<div class="space-y-6" x-data="{
    copyIp(ip) {
        if (!ip) return;
        navigator.clipboard.writeText(ip);
        showAlert('IP ' + ip + ' disalin ke clipboard', { icon: 'success', title: 'Berhasil disalin' });
    }
}">
    <!-- Stat Cards -->
    <div class="grid grid-cols-1 {{ $pendingOrdersCount > 0 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3' }} gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-wider font-bold text-muted">VPS Instances</p>
                <p class="font-mono text-2xl font-bold text-ink mt-1">{{ $vps->count() }} <span class="font-sans text-xs font-normal text-muted">Unit</span></p>
                <p class="text-[11px] text-muted mt-1">
                    {{ $vps->where('status', 'running')->count() }} Aktif · {{ $vps->whereIn('status', ['suspended', 'stopped', 'error'])->count() }} Perlu Perhatian
                    @if(isset($provisioningOrders) && $provisioningOrders->count() > 0)
                        · <span class="text-amber-700 font-bold">{{ $provisioningOrders->count() }} Setup</span>
                    @endif
                </p>
            </div>
            <div class="w-10 h-10 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
        </div>

        @if($pendingOrdersCount > 0)
        <div class="bg-amber-50 p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-wider font-bold text-amber-800">Pesanan Diproses</p>
                <p class="font-mono text-2xl font-bold text-amber-950 mt-1">{{ $pendingOrdersCount }} <span class="font-sans text-xs font-normal text-amber-800">Order</span></p>
                <p class="text-[11px] text-amber-800 mt-1">Sedang dalam setup server oleh CravaCloud</p>
            </div>
            <div class="w-10 h-10 bg-amber-100 border border-amber-400 text-amber-800 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        @endif

        <div class="bg-white p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-wider font-bold text-muted">Faktur &amp; Tagihan</p>
                <p class="font-mono text-2xl font-bold text-ink mt-1">{{ $invoicesCount }} <span class="font-sans text-xs font-normal text-muted">Faktur</span></p>
                <a href="{{ route('dashboard.billing') }}" class="font-mono text-[11px] font-bold uppercase text-electric hover:underline mt-1 inline-block">
                    Lihat faktur →
                </a>
            </div>
            <div class="w-10 h-10 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-wider font-bold text-muted">Tiket Bantuan</p>
                <p class="font-mono text-2xl font-bold text-ink mt-1">{{ $openTicketsCount }} <span class="font-sans text-xs font-normal text-muted">Aktif</span></p>
                <a href="{{ route('dashboard.support') }}" class="font-mono text-[11px] font-bold uppercase text-electric hover:underline mt-1 inline-block">
                    Buka tiket →
                </a>
            </div>
            <div class="w-10 h-10 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>
    </div>

    <!-- Unpaid Orders Section -->
    @if($unpaidOrders->count() > 0)
    <div class="bg-white border-2 border-ink p-5 sm:p-6">
        <div class="flex items-center gap-3 pb-4 mb-4 border-b border-hairline">
            <div class="w-9 h-9 bg-amber-100 border border-amber-500 text-amber-900 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <div>
                <h3 class="font-display font-bold text-ink text-base">Layanan Terkunci - Menunggu Pembayaran</h3>
                <p class="text-xs text-muted">Anda memiliki {{ $unpaidOrders->count() }} pesanan yang belum dibayar.</p>
            </div>
        </div>

        <div class="space-y-4">
            @foreach($unpaidOrders as $order)
            <div class="bg-creamDark/40 border border-hairline p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 mb-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="font-display font-bold text-ink text-base">{{ $order->vpsSpec->name }}</h4>
                            @if($order->hostname)
                                <span class="px-2 py-0.5 font-mono text-[11px] font-bold bg-white text-ink border border-hairline">
                                    {{ $order->hostname }}
                                </span>
                            @endif
                            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-amber-100 text-amber-950 border border-amber-400">
                                TERKUNCI
                            </span>
                        </div>
                        <p class="font-mono text-[11px] text-muted mt-1">
                            {{ $order->vpsSpec->cpu }} vCPU · {{ $order->vpsSpec->ram }} GB RAM · {{ $order->vpsSpec->disk }} GB SSD
                        </p>
                    </div>
                    <div class="sm:text-right">
                        <div class="font-mono text-[10px] uppercase text-muted">Total Tagihan</div>
                        <div class="font-bold text-lg font-mono text-electric">Rp {{ number_format($order->amount, 0, ',', '.') }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs mb-4 pt-3 border-t border-hairline">
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">Provider &amp; DC</span>
                        <span class="font-semibold text-ink capitalize">{{ $order->provider_label }} · {{ $order->datacenter_location }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">OS &amp; Stack</span>
                        <span class="font-semibold text-ink">{{ $order->os_label }} · {{ $order->control_panel_label }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">Metode Bayar</span>
                        <span class="font-mono font-bold text-ink">{{ strtoupper($order->payment_method) }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">Invoice</span>
                        <span class="font-mono font-bold text-ink">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-2.5">
                    <a href="{{ \App\Models\PaymentGateway::isMidtransMethod($order->payment_method) ? route('order.payment.status', $order->id) : route('order.payment', $order->id) }}"
                       class="flex-1 rounded-none py-2.5 px-4 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider text-center transition-colors">
                        Selesaikan Pembayaran
                    </a>
                    <a href="{{ route('order.success', $order->id) }}"
                       class="rounded-none py-2.5 px-4 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider text-center transition-colors">
                        Lihat Detail
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-4 p-3.5 bg-white border border-hairline text-xs text-muted">
            <span class="font-bold text-ink">Catatan:</span> Layanan VPS akan terkunci sampai pembayaran dikonfirmasi. Setelah lunas, tim teknis melakukan setup server maksimal 1x24 jam.
        </div>
    </div>
    @endif

    <!-- VPS List Section -->
    <div id="provisioning-orders-section" class="space-y-4 pt-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-hairline pb-4">
            <div>
                <h2 class="font-display text-lg font-bold text-ink">Daftar Server Cloud VPS</h2>
                <p class="text-xs text-muted">Kelola instance Cloud VPS Anda.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('home') }}#pricing" class="rounded-none inline-flex items-center gap-1.5 px-3.5 py-2 border border-ink bg-white hover:bg-electric hover:text-white hover:border-electric text-ink font-mono text-[11px] font-bold uppercase transition-colors">
                    <span>+ Tambah VPS</span>
                </a>
            </div>
        </div>

        @if($vps->count() === 0 && (!isset($provisioningOrders) || $provisioningOrders->count() === 0))
            <div class="bg-white border border-ink p-8 sm:p-10 text-center max-w-lg mx-auto">
                <div class="w-12 h-12 bg-creamDark border border-hairline text-ink flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                </div>
                <h3 class="font-display text-base font-bold text-ink mb-1">Belum Ada Server Aktif</h3>
                <p class="text-xs text-muted leading-relaxed mb-6">
                    Instance Cloud VPS Anda belum terdaftar. Pilih paket VPS untuk memulai deployment:
                </p>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <a href="{{ route('home') }}#pricing" class="rounded-none px-4 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase transition-colors">
                        + Tambah VPS
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                {{-- Provisioning Orders --}}
                @if(isset($provisioningOrders))
                    @foreach($provisioningOrders as $order)
                        <div id="order-card-{{ $order->id }}" class="bg-white border-2 border-amber-500 p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-display font-bold text-ink text-base">{{ $order->hostname ?? 'cv-server-' . $order->id }}</h3>
                                            @if($order->isDatabasePackage())
                                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400">Managed DB</span>
                                            @elseif($order->isAiPackage())
                                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">AI Agent</span>
                                            @else
                                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">{{ $order->vpsSpec->name ?? 'Cloud VPS' }}</span>
                                            @endif
                                        </div>
                                        <p class="font-mono text-[11px] text-muted mt-0.5">Setup Server Sedang Berjalan</p>
                                    </div>

                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-amber-100 text-amber-900 border border-amber-400 shrink-0">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                        Dalam Setup
                                    </span>
                                </div>

                                <div class="mb-3 p-3 bg-creamDark/50 border border-hairline text-xs space-y-1">
                                    <div class="flex items-center justify-between font-bold text-ink">
                                        <span class="flex items-center gap-1.5">Proses Setup Server</span>
                                        <span class="font-mono text-[11px] text-muted">Order #{{ $order->id }}</span>
                                    </div>
                                    <p class="text-[11px] text-muted leading-relaxed">
                                        @if($order->latestProvisioningTask && $order->latestProvisioningTask->provider === 'tencent')
                                            Pembayaran terverifikasi. Sistem sedang mengalokasikan server dan memasang stack secara otomatis (~2–5 menit).
                                        @else
                                            Pesanan terverifikasi. Server sedang dialokasikan dan dikonfigurasi (maksimal 1x24 jam sejak pembayaran, umumnya lebih cepat).
                                        @endif
                                    </p>
                                </div>

                                <div class="bg-creamDark/30 p-3 my-2 font-mono text-[11px] space-y-1.5 border border-hairline">
                                    <div class="flex justify-between gap-2">
                                        <span class="text-muted">Compute</span>
                                        <span class="font-bold text-ink text-right">{{ $order->vpsSpec->cpu ?? 1 }} vCPU · {{ $order->vpsSpec->ram ?? 1 }} GB · {{ $order->vpsSpec->disk ?? 20 }} GB</span>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <span class="text-muted">Provider &amp; DC</span>
                                        <span class="font-semibold text-ink capitalize text-right">{{ $order->provider_label }} · {{ $order->datacenter_location }}</span>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <span class="text-muted">OS &amp; Stack</span>
                                        <span class="font-semibold text-ink text-right">{{ $order->os_label }} · {{ $order->control_panel_label }}</span>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <span class="text-muted">Pembayaran</span>
                                        <span class="font-bold text-emerald-700 text-right">LUNAS · Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-hairline flex items-center gap-2 mt-2">
                                <a href="{{ route('order.success', $order->id) }}"
                                   class="flex-1 rounded-none py-2.5 px-3 text-center bg-ink hover:bg-electric text-white font-mono text-[11px] font-bold uppercase transition-colors">
                                    Lihat Detail &amp; Faktur
                                </a>
                                <a href="{{ route('dashboard.support') }}"
                                   class="rounded-none py-2.5 px-3 text-center border border-ink hover:bg-creamDark text-ink font-mono text-[11px] font-bold uppercase transition-colors">
                                    Bantuan
                                </a>
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- Active / Existing VPS Instances --}}
                @foreach($vps as $instance)
                    <div class="bg-white border {{ $instance->isDatabasePackage() ? 'border-2 border-emerald-600' : ($instance->isAiPackage() ? 'border-2 border-electric' : 'border-hairline hover:border-ink') }} p-5 transition-colors flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-display font-bold text-ink text-base truncate">{{ $instance->hostname ?? 'vps-' . $instance->id }}</h3>
                                        @if($instance->isDatabasePackage())
                                            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400">Managed DB</span>
                                        @elseif($instance->isAiPackage())
                                            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">AI Agent</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="text-xs font-mono font-bold text-ink">
                                            {{ $instance->public_ip ?? 'Mengalokasikan IP...' }}
                                        </span>
                                        @if($instance->public_ip)
                                            <button type="button" @click="copyIp('{{ $instance->public_ip }}')" title="Salin IP" class="text-muted hover:text-electric cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @php $instanceStatus = $instance->customer_status; @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase border {{ $instanceStatus['class'] }} shrink-0" title="Status layanan">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $instanceStatus['dot'] }}"></span>
                                    {{ $instanceStatus['label'] }}
                                </span>
                            </div>

                            <!-- Subscription & Expiry Tag -->
                            <div class="mb-3">
                                @if($instance->status === 'suspended')
                                    <span class="inline-block px-2 py-0.5 font-mono text-[11px] font-bold bg-amber-100 text-amber-950 border border-amber-400">
                                        Layanan Ditangguhkan (Suspended)
                                    </span>
                                @elseif($instance->isInGracePeriod())
                                    <span class="inline-block px-2 py-0.5 font-mono text-[11px] font-bold bg-amber-100 text-amber-950 border border-amber-400">
                                        Masa Tenggang s/d {{ $instance->grace_period_ends_at?->timezone('Asia/Jakarta')->format('d M') }}
                                    </span>
                                @elseif($instance->isExpired())
                                    <span class="inline-block px-2 py-0.5 font-mono text-[11px] font-bold bg-red-100 text-red-900 border border-red-400">
                                        Expired {{ $instance->expires_at?->timezone('Asia/Jakarta')->format('d M Y') }}
                                    </span>
                                @elseif($instance->expires_at)
                                    <span class="inline-block px-2 py-0.5 font-mono text-[11px] bg-creamDark text-ink border border-hairline">
                                        Tempo: {{ $instance->expires_at->timezone('Asia/Jakarta')->format('d M Y') }} ({{ $instance->days_until_expiry }} hr)
                                    </span>
                                @endif
                            </div>

                            <!-- Hardware Specs -->
                            <div class="bg-creamDark/40 p-3 my-2 font-mono text-[11px] space-y-1 border border-hairline">
                                <div class="flex justify-between">
                                    <span class="text-muted">Compute</span>
                                    <span class="font-bold text-ink">{{ $instance->cpu ?? 1 }} vCPU · {{ $instance->ram ?? 1 }} GB RAM</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted">Storage</span>
                                    <span class="font-bold text-ink">{{ $instance->disk ?? 20 }} GB NVMe SSD</span>
                                </div>
                            </div>

                            <!-- Meta Info -->
                            <div class="space-y-1.5 text-xs text-muted mb-3 pt-1">
                                <div class="flex justify-between gap-2">
                                    <span>OS</span>
                                    <span class="font-semibold text-ink text-right">{{ $instance->os_label ?: ($instance->os ?? 'Ubuntu 24.04 LTS') }}</span>
                                </div>
                                <div class="flex justify-between items-center gap-2">
                                    <span>Stack / Panel</span>
                                    <span class="font-mono font-bold text-ink uppercase text-[11px] text-right">{{ $instance->control_panel_label }}</span>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <span>Siklus</span>
                                    <span class="font-mono font-semibold text-ink uppercase text-[11px] text-right">{{ str_replace('_', ' ', $instance->billing_cycle ?? 'monthly') }}</span>
                                </div>
                            </div>

                            @if($instance->isAiPackage())
                                <div class="my-3 p-3 bg-creamDark/60 border border-electric text-xs space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono font-bold text-ink text-[11px] uppercase flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span>Link Web App:</span>
                                        </span>
                                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 bg-electric text-white">Web App</span>
                                    </div>
                                    @if($instance->app_url && $instance->status === 'running')
                                        <div class="flex items-center justify-between gap-1.5 p-2 bg-white border border-hairline font-mono text-[11px]">
                                            <a href="{{ $instance->app_url }}" target="_blank" rel="noopener" class="text-electric font-bold hover:underline truncate">
                                                {{ $instance->app_url }}
                                            </a>
                                            <button type="button" @click="copyIp('{{ $instance->app_url }}')" title="Salin Link App" class="text-muted hover:text-ink shrink-0 cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                    @else
                                        <p class="text-[11px] text-muted italic">
                                            {{ $instance->status === 'running' ? ($instance->provider === 'tencent' ? 'Tautan web sedang disiapkan otomatis.' : 'Tautan web sedang disiapkan tim.') : 'Layanan sedang tidak aktif. Buka detail untuk informasi lebih lanjut.' }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2 pt-3 border-t border-hairline">
                            @if($instance->isAiPackage() && $instance->app_url && $instance->status === 'running')
                                <a href="{{ $instance->app_url }}" target="_blank" rel="noopener"
                                   class="flex-1 rounded-none py-2.5 px-3 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase text-center transition-colors inline-flex items-center justify-center gap-1.5">
                                    <span>Buka Web App</span>
                                    <span>↗</span>
                                </a>
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}"
                                   class="rounded-none py-2.5 px-3 border border-ink hover:bg-creamDark text-ink font-mono text-[11px] font-bold uppercase text-center transition-colors">
                                    Detail
                                </a>
                            @else
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}"
                                   class="flex-1 rounded-none py-2.5 px-3 bg-ink hover:bg-electric text-white font-mono text-[11px] font-bold uppercase text-center transition-colors">
                                    Kelola Layanan
                                </a>
                                @if($instance->canBeRenewed())
                                    <a href="{{ route('dashboard.vps.show', $instance->id) }}"
                                       title="Perpanjang Layanan"
                                       class="rounded-none py-2.5 px-3 border border-ink hover:bg-creamDark text-ink font-mono text-[11px] font-bold uppercase text-center transition-colors">
                                        Perpanjang
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@if(isset($paymentSuccessOrder) && $paymentSuccessOrder)
@push('scripts')
<script>
(function() {
    function initPaymentSuccessModal() {
        if (!window.Swal) {
            setTimeout(initPaymentSuccessModal, 100);
            return;
        }

        if (window.history && window.history.replaceState) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('payment_success') || currentUrl.searchParams.has('order_id')) {
                currentUrl.searchParams.delete('payment_success');
                currentUrl.searchParams.delete('order_id');
                window.history.replaceState({}, document.title, currentUrl.pathname + (currentUrl.search ? currentUrl.search : ''));
            }
        }

        window.Swal.fire({
            title: '<div class="font-display text-xl sm:text-2xl font-bold text-[#121212] mt-2">Pembayaran Berhasil Dikonfirmasi!</div>',
            html: `
                <div class="text-left mt-3 space-y-3.5 text-xs text-[#686763]">
                    <div class="p-3 bg-emerald-50 border border-emerald-500 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-mono uppercase text-emerald-900 font-bold block">Status Pembayaran</span>
                            <span class="text-sm font-mono font-bold text-emerald-900">LUNAS</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-emerald-900 block font-mono">{{ $paymentSuccessOrder->invoice?->invoice_number ?? ('INV-' . $paymentSuccessOrder->id) }}</span>
                            <span class="text-sm font-bold text-[#121212] font-mono">Rp {{ number_format($paymentSuccessOrder->amount, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="bg-[#EFECE4] border border-[#DEDAD0] p-3.5 space-y-2">
                        <div class="flex justify-between border-b border-[#DEDAD0] pb-1.5">
                            <span>Layanan</span>
                            <span class="font-bold text-[#121212]">{{ $paymentSuccessOrder->vpsSpec?->name ?? 'Cloud VPS' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-[#DEDAD0] pb-1.5">
                            <span>Server Hostname</span>
                            <span class="font-mono font-bold text-[#121212]">{{ $paymentSuccessOrder->hostname ?? ('cv-server-' . $paymentSuccessOrder->id) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-[#DEDAD0] pb-1.5">
                            <span>OS &amp; Stack</span>
                            <span class="font-semibold text-[#121212]">{{ $paymentSuccessOrder->os_label }} · {{ $paymentSuccessOrder->control_panel_label }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Datacenter</span>
                            <span class="font-semibold text-[#121212] capitalize">{{ $paymentSuccessOrder->provider_label }} · {{ $paymentSuccessOrder->datacenter_location }}</span>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 border border-amber-500 text-amber-950 text-[11px] leading-relaxed">
                        <div class="font-mono font-bold mb-1 uppercase">Server Sedang Dalam Antrean Setup</div>
                        Pesanan masuk ke tim teknis CravaCloud (maksimal 1x24 jam sejak pembayaran, umumnya lebih cepat). Kredensial akses otomatis dikirimkan ke email Anda.
                    </div>
                </div>
            `,
            icon: 'success',
            iconColor: '#001AFF',
            confirmButtonText: 'Pantau Status Server',
            confirmButtonColor: '#001AFF',
            showCancelButton: true,
            cancelButtonText: 'Tutup',
            cancelButtonColor: '#121212',
            customClass: {
                popup: 'rounded-none border-2 border-[#121212] bg-[#F8F6F0] p-6 sm:p-7 max-w-lg',
                confirmButton: 'rounded-none px-5 py-2.5 font-mono font-bold text-xs uppercase bg-[#001AFF] text-white',
                cancelButton: 'rounded-none px-4 py-2.5 font-mono font-semibold text-xs uppercase',
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const targetCard = document.getElementById('order-card-{{ $paymentSuccessOrder->id }}') || document.getElementById('provisioning-orders-section');
                if (targetCard) {
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetCard.classList.add('ring-2', 'ring-electric', 'ring-offset-2');
                    setTimeout(() => {
                        targetCard.classList.remove('ring-2', 'ring-electric', 'ring-offset-2');
                    }, 3000);
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaymentSuccessModal);
    } else {
        initPaymentSuccessModal();
    }
})();
</script>
@endpush
@endif
