@extends('layouts.admin', ['title' => 'Admin Console', 'headerTitle' => 'Dashboard Operasional'])

@section('content')
<div class="space-y-6">

    <!-- Cluster Status Header -->
    <div class="bg-white border border-hairline p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-4">
            <div class="w-11 h-11 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="font-display text-base sm:text-lg font-bold text-ink">CravaCloud Node Cluster</h2>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider bg-electric text-white">
                        <span class="w-1.5 h-1.5 bg-white"></span>
                        Semua Sistem Beroperasi
                    </span>
                </div>
                <p class="text-xs text-muted mt-1">
                    Datacenter: <strong class="text-ink">Jakarta (DCI-ID)</strong> &amp; <strong class="text-ink">Singapore</strong> &middot; Total VPS Aktif: <span class="font-mono font-bold text-ink">{{ $active_vps }} unit</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="{{ route('admin.shopee') }}" class="px-4 py-2 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-1.5">
                <span>+ Proses Shopee</span>
            </a>
            <a href="{{ route('admin.orders') }}" class="px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                Antrean Order ({{ $pending_orders }})
            </a>
        </div>
    </div>

    <!-- KPI Metric Grid (PRD 7.3.6: Action Needed, Running Auto vs Manual, Upstream Balance & Quota, Monthly Revenue & Margin) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <!-- KPI 1: Perlu Tindakan Admin -->
        <div class="bg-white p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">Perlu Tindakan Admin</p>
                <p class="font-display text-3xl font-bold {{ ($actionNeededTotal ?? 0) > 0 ? 'text-red-600' : 'text-ink' }} mt-1">{{ $actionNeededTotal ?? $pending_orders }}</p>
                <div class="font-mono text-[10px] text-muted mt-1 space-x-1">
                    <span>Gagal: {{ $ordersFailed ?? 0 }}</span> &middot;
                    <span>Manual: {{ $ordersNeedsManual ?? 0 }}</span> &middot;
                    <span>Tiket: {{ $openTicketsCount ?? 0 }}</span>
                </div>
                <a href="{{ route('admin.orders') }}?status=needs_provision" class="font-mono text-[11px] font-bold text-electric hover:underline mt-1 inline-block">
                    Buka Antrean &rarr;
                </a>
            </div>
            <div class="w-10 h-10 {{ ($actionNeededTotal ?? 0) > 0 ? 'bg-red-50 text-red-600 border-red-200' : 'bg-creamDark text-ink' }} border border-hairline flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 004.99 19z"/></svg>
            </div>
        </div>

        <!-- KPI 2: VPS Running (Auto vs Manual) -->
        <div class="bg-white p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">VPS Running</p>
                <p class="font-display text-3xl font-bold text-ink mt-1">{{ $vpsRunningTotal ?? $active_vps }}</p>
                <p class="font-mono text-[11px] text-muted mt-1">
                    <span class="text-emerald-700 font-semibold">Tencent: {{ $vpsRunningAuto ?? 0 }}</span> &middot;
                    <span>Manual: {{ $vpsRunningManual ?? 0 }}</span>
                </p>
                <a href="{{ route('admin.instances') }}" class="font-mono text-[11px] font-bold text-electric hover:underline mt-1 inline-block">
                    Kelola VPS &rarr;
                </a>
            </div>
            <div class="w-10 h-10 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
        </div>

        <!-- KPI 3: Saldo Tencent & Kuota Region -->
        <div class="bg-white p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">Saldo Tencent &amp; Kuota</p>
                <p class="font-display text-2xl font-bold font-mono text-ink mt-1">${{ number_format($balanceUsd ?? 0, 2) }}</p>
                <p class="font-mono text-[10px] text-muted mt-1">
                    JKT: {{ $jktQuota ?? '-' }} &middot; SGP: {{ $sgpQuota ?? '-' }}
                </p>
                <a href="{{ route('admin.monitoring') }}?tab=upstream" class="font-mono text-[11px] font-bold text-electric hover:underline mt-1 inline-block">
                    Monitor Quota &rarr;
                </a>
            </div>
            <div class="w-10 h-10 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
        </div>

        <!-- KPI 4: Omzet & Margin Riil Bulan Ini -->
        <div class="bg-white p-5 flex items-center justify-between">
            <div>
                <p class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">Omzet &amp; Margin Bulan Ini</p>
                <p class="font-display text-2xl font-bold text-ink mt-1">Rp {{ number_format($monthlyRevenue ?? $total_revenue, 0, ',', '.') }}</p>
                <p class="font-mono text-[11px] text-muted mt-1">
                    Margin Riil: <span class="font-bold text-emerald-700">{{ $monthlyMarginPercent ?? 0 }}%</span>
                </p>
                <a href="{{ route('admin.billing.index') }}" class="font-mono text-[11px] font-bold text-electric hover:underline mt-1 inline-block">
                    Billing Center &rarr;
                </a>
            </div>
            <div class="w-10 h-10 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    <!-- Dual Channel Performance, Package Distribution & Open Tickets -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Channel Penjualan -->
        <div class="bg-white border border-hairline p-5 space-y-4">
            <h3 class="font-display text-sm font-bold text-ink pb-2.5 border-b border-hairline uppercase tracking-wider">Channel Penjualan</h3>

            <div class="space-y-3 text-xs">
                <div class="p-3 bg-creamDark/40 border border-hairline flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white border border-hairline flex items-center justify-center p-1 shrink-0">
                            <img src="{{ asset('images/logo.svg') }}" alt="CravaCloud" class="w-full h-full object-contain">
                        </div>
                        <div>
                            <span class="font-display font-bold text-ink block">Website Direct</span>
                            <span class="text-muted text-[11px]">Midtrans Snap</span>
                        </div>
                    </div>
                    <span class="font-mono font-bold text-ink">{{ $orders_web }} Order</span>
                </div>

                <div class="p-3 bg-creamDark/40 border border-hairline flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white border border-hairline flex items-center justify-center p-1.5 shrink-0">
                            <img src="{{ asset('images/payments/shopee.svg') }}" alt="Shopee" class="w-full h-full object-contain">
                        </div>
                        <div>
                            <span class="font-display font-bold text-ink block">Shopee Marketplace</span>
                            <span class="text-muted text-[11px]">Order auto-credential</span>
                        </div>
                    </div>
                    <span class="font-mono font-bold text-ink">{{ $orders_shopee }} Order</span>
                </div>
            </div>

            <a href="{{ route('admin.shopee') }}" class="w-full py-2.5 px-3 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-1.5 transition-colors">
                <span>+ Input Transaksi Shopee</span>
            </a>
        </div>

        <!-- Popularitas Paket -->
        <div class="bg-white border border-hairline p-5 space-y-4">
            <h3 class="font-display text-sm font-bold text-ink pb-2.5 border-b border-hairline uppercase tracking-wider">Distribusi Paket VPS</h3>

            <div class="space-y-3 text-xs">
                @foreach($specs as $spec)
                    <div>
                        <div class="flex justify-between items-center mb-1 font-medium">
                            <span class="text-ink font-semibold">{{ $spec->name }}</span>
                            <span class="font-mono text-muted font-bold">{{ $spec->orders_count }} order</span>
                        </div>
                        <div class="w-full bg-creamDark h-2 overflow-hidden border border-hairline">
                            <div class="bg-electric h-full" style="width: {{ $specs->sum('orders_count') > 0 ? ($spec->orders_count / $specs->sum('orders_count')) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Open Support Tickets -->
        <div class="bg-white border border-hairline p-5 space-y-3 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-2.5 border-b border-hairline mb-3">
                    <h3 class="font-display text-sm font-bold text-ink uppercase tracking-wider">Tiket Dukungan Masuk</h3>
                    <a href="{{ route('admin.tickets') }}" class="font-mono text-[11px] text-electric font-bold hover:underline">Semua &rarr;</a>
                </div>

                <div class="space-y-2 text-xs">
                    @forelse($open_tickets as $ticket)
                        <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="p-3 border border-hairline hover:border-ink hover:bg-creamDark/40 block transition-colors">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="font-bold text-ink truncate max-w-[160px]">{{ $ticket->subject }}</span>
                                <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase {{ $ticket->priority === 'high' ? 'bg-ink text-cream' : 'bg-creamDark border border-hairline text-ink' }}">
                                    {{ $ticket->priority }}
                                </span>
                            </div>
                            <span class="text-muted font-mono text-[11px]">{{ $ticket->customer->full_name }} &middot; {{ $ticket->created_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p class="text-muted text-center py-6">Tidak ada tiket terbuka.</p>
                    @endforelse
                </div>
            </div>

            <a href="{{ route('admin.tickets') }}" class="w-full py-2.5 px-3 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider text-center block transition-colors">
                Buka Helpdesk Admin
            </a>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="p-5 border-b border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Pesanan Terbaru</h3>
                <p class="text-xs text-muted mt-0.5">Daftar transaksi masuk dari Website dan Shopee</p>
            </div>
            <a href="{{ route('admin.orders') }}" class="font-mono text-xs font-bold text-electric hover:underline">
                Lihat Semua Antrean &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                    <tr>
                        <th class="px-5 py-3">ID Order</th>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Channel</th>
                        <th class="px-5 py-3">Paket</th>
                        <th class="px-5 py-3">Panel</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Total</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach($recent_orders as $order)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5 font-mono font-bold text-ink">#ORD-{{ $order->id }}</td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-ink block">{{ $order->customer->full_name }}</span>
                                <span class="text-muted font-mono text-[11px]">{{ $order->customer->email }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-creamDark/60 text-ink">
                                    {{ $order->channel === 'shopee' ? 'Shopee' : 'Website' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $order->vpsSpec->name ?? 'Standard' }}</td>
                            <td class="px-5 py-3.5 text-muted font-mono text-[11px]">{{ $order->control_panel_label }}</td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase
                                    @if($order->status === 'active') bg-electric text-white
                                    @elseif($order->status === 'pending') bg-creamDark border border-ink text-ink
                                    @elseif($order->status === 'cancelled') bg-ink text-cream
                                    @else bg-white border border-hairline text-ink
                                    @endif">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-mono font-bold text-ink">Rp {{ number_format($order->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.orders') }}" class="font-mono text-xs text-electric font-bold hover:underline">
                                    Detail &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection