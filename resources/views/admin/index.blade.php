@extends('layouts.admin', ['title' => 'Admin Console', 'headerTitle' => 'Dashboard Operasional'])

@section('content')
<div class="space-y-6">

    <!-- Hostinger hPanel Style Server Status Header Card -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold text-lg shrink-0 border border-blue-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-bold text-slate-900">CravaCloud Reseller Node Cluster</h2>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Semua Sistem Beroperasi
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Datacenter: <strong>Singapore</strong> & <strong>Jakarta</strong> &bull; Total VPS Aktif: <span class="font-bold text-slate-800">{{ $active_vps }} unit</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ route('admin.shopee') }}" class="px-4 py-2 rounded-lg bg-orange-600 hover:bg-orange-700 text-white font-semibold text-xs shadow-xs transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 53 61" fill="currentColor" fill-rule="evenodd" xmlns="http://www.w3.org/2000/svg">
                    <path d="M35.67174 44.953764c-.333349 2.751051-2.000311 4.954341-4.582384 6.057598-1.437971.614592-3.36871.946386-4.896954.842163-2.384027-.09111-4.623787-.670894-6.688335-1.730742-.737553-.378855-1.837052-1.135276-2.68131-1.843776-.213839-.179005-.239235-.293758-.09774-.494467.0764-.115182.217254-.322983.528622-.779199.45158-.661654.507921-.744602.558713-.822178.14448-.221769.379233-.241109.610785-.05888.02433.01891.02433.01891.04268.03331.03799.02944.03799.02944.12762.09907.0907.0707.14448.112389.166248.12872 2.226529 1.743851 4.819699 2.749547 7.437625 2.850117 3.642304-.04964 6.261511-1.687335 6.730804-4.202004.516031-2.767598-1.656504-5.158274-5.907033-6.490821-1.329344-.416676-4.689518-1.761687-5.309053-2.12507-2.909447-1.707104-4.269736-3.943058-4.076384-6.704854.296216-3.828306 3.850167-6.683579 8.340785-6.702705 2.008208-.0041 4.012147.413238 5.937338 1.224457.681638.28731 1.898727.949608 2.318936 1.263351.242009.177716.289813.384872.151095.60836-.07747.12958-.205515.335017-.475482.763298l-.003.0047c-.355331.564092-.366428.581714-.447952.713657-.140852.214463-.306459.234448-.56042.07328-2.060067-1.383907-4.34379-2.080157-6.855437-2.130442-3.126914.06189-5.470605 1.922856-5.624689 4.45794-.04097 2.289677 1.676352 3.961324 5.385881 5.23585 7.529819 2.419687 10.411309 5.25648 9.869029 9.729248M26.372522 5.4266937c4.902289 0 8.898217 4.6522033 9.085166 10.4757833H17.287569c.186949-5.82358 4.182877-10.4757833 9.084953-10.4757833M51.743379 16.997353c0-.604706-.487007-1.094876-1.087549-1.094876H38.87847c-.28896-7.6892751-5.777492-13.8205815-12.505948-13.8205815-6.728244 0-12.216776 6.1313064-12.505736 13.8205815l-11.7942195.000215c-.5913649.01075-1.0674873.496831-1.0674873 1.094661 0 .02858.00107.05695.0032.08488h-.008323l1.6812616 37.061399c.0002134.103148.00405.207156.011738.311809.00171.02364.00363.04706.00555.07048l.00363.07822.00405.0041c.2554543 2.578923 2.1270784 4.656071 4.6720177 4.751913l.00576.0056H44.796175c.01771.000215.03543.00043.05314.00043.01771 0 .03543-.000215.05314-.00043h.0796l.0017-.0015c2.589329-.0707 4.686743-2.176859 4.908265-4.787585l.0013-.0013.0017-.03503c.0021-.02751.0041-.0548.0058-.0823.0041-.06576.0068-.1313.0079-.196412l1.83449-37.207738h-.0013c.0011-.0187.0015-.03761.0015-.05652"/>
                </svg>
                <span>+ Proses Shopee</span>
            </a>
            <a href="{{ route('admin.orders') }}" class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition-colors">
                Antrean Order ({{ $pending_orders }})
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500">Antrean Pending</p>
                <p class="text-2xl font-bold text-amber-600 mt-1 font-mono-code">{{ $pending_orders }}</p>
                <a href="{{ route('admin.orders') }}?status=pending" class="text-[11px] text-[#4A6FA5] font-medium hover:underline mt-1 inline-block">
                    Perlu provisioning &rarr;
                </a>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500">VPS Berjalan</p>
                <p class="text-2xl font-bold text-[#6ABD73] mt-1 font-mono-code">{{ $active_vps }}</p>
                <p class="text-[11px] text-slate-400 mt-1">Instance berstatus running</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-[#6ABD73] flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500">Total Pelanggan</p>
                <p class="text-2xl font-bold text-slate-800 mt-1 font-mono-code">{{ $total_customers }}</p>
                <p class="text-[11px] text-slate-400 mt-1">Web: {{ $orders_web }} &bull; Shopee: {{ $orders_shopee }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500">Estimasi Omzet</p>
                <p class="text-2xl font-bold text-slate-900 mt-1 font-mono-code">Rp {{ number_format($total_revenue, 0, ',', '.') }}</p>
                <p class="text-[11px] text-slate-400 mt-1">Faktur terbayar aktif</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    <!-- Dual Channel Performance & Package Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Channel Penjualan -->
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs space-y-4">
            <h3 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">Channel Penjualan</h3>

            <div class="space-y-3 text-xs">
                <div class="p-3 rounded-lg bg-blue-50/60 border border-blue-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-white border border-slate-200/80 flex items-center justify-center p-1 shrink-0 shadow-xs">
                            <img src="{{ asset('images/logo.png') }}" alt="CravaCloud" class="w-full h-full object-contain">
                        </div>
                        <div>
                            <span class="font-bold text-slate-900 block">Website Direct</span>
                            <span class="text-slate-500 text-[11px]">Midtrans SNAP & QRIS</span>
                        </div>
                    </div>
                    <span class="font-mono-code font-bold text-slate-900">{{ $orders_web }} Order</span>
                </div>

                <div class="p-3 rounded-lg bg-orange-50/60 border border-orange-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-white border border-slate-200/80 flex items-center justify-center p-1.5 shrink-0 shadow-xs">
                            <img src="{{ asset('images/payments/shopee.svg') }}" alt="Shopee" class="w-full h-full object-contain">
                        </div>
                        <div>
                            <span class="font-bold text-slate-900 block">Shopee Marketplace</span>
                            <span class="text-slate-500 text-[11px]">Order auto-credential</span>
                        </div>
                    </div>
                    <span class="font-mono-code font-bold text-slate-900">{{ $orders_shopee }} Order</span>
                </div>
            </div>

            <a href="{{ route('admin.shopee') }}" class="w-full py-2 px-3 rounded-lg bg-orange-600 hover:bg-orange-700 text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 53 61" fill="currentColor" fill-rule="evenodd" xmlns="http://www.w3.org/2000/svg">
                    <path d="M35.67174 44.953764c-.333349 2.751051-2.000311 4.954341-4.582384 6.057598-1.437971.614592-3.36871.946386-4.896954.842163-2.384027-.09111-4.623787-.670894-6.688335-1.730742-.737553-.378855-1.837052-1.135276-2.68131-1.843776-.213839-.179005-.239235-.293758-.09774-.494467.0764-.115182.217254-.322983.528622-.779199.45158-.661654.507921-.744602.558713-.822178.14448-.221769.379233-.241109.610785-.05888.02433.01891.02433.01891.04268.03331.03799.02944.03799.02944.12762.09907.0907.0707.14448.112389.166248.12872 2.226529 1.743851 4.819699 2.749547 7.437625 2.850117 3.642304-.04964 6.261511-1.687335 6.730804-4.202004.516031-2.767598-1.656504-5.158274-5.907033-6.490821-1.329344-.416676-4.689518-1.761687-5.309053-2.12507-2.909447-1.707104-4.269736-3.943058-4.076384-6.704854.296216-3.828306 3.850167-6.683579 8.340785-6.702705 2.008208-.0041 4.012147.413238 5.937338 1.224457.681638.28731 1.898727.949608 2.318936 1.263351.242009.177716.289813.384872.151095.60836-.07747.12958-.205515.335017-.475482.763298l-.003.0047c-.355331.564092-.366428.581714-.447952.713657-.140852.214463-.306459.234448-.56042.07328-2.060067-1.383907-4.34379-2.080157-6.855437-2.130442-3.126914.06189-5.470605 1.922856-5.624689 4.45794-.04097 2.289677 1.676352 3.961324 5.385881 5.23585 7.529819 2.419687 10.411309 5.25648 9.869029 9.729248M26.372522 5.4266937c4.902289 0 8.898217 4.6522033 9.085166 10.4757833H17.287569c.186949-5.82358 4.182877-10.4757833 9.084953-10.4757833M51.743379 16.997353c0-.604706-.487007-1.094876-1.087549-1.094876H38.87847c-.28896-7.6892751-5.777492-13.8205815-12.505948-13.8205815-6.728244 0-12.216776 6.1313064-12.505736 13.8205815l-11.7942195.000215c-.5913649.01075-1.0674873.496831-1.0674873 1.094661 0 .02858.00107.05695.0032.08488h-.008323l1.6812616 37.061399c.0002134.103148.00405.207156.011738.311809.00171.02364.00363.04706.00555.07048l.00363.07822.00405.0041c.2554543 2.578923 2.1270784 4.656071 4.6720177 4.751913l.00576.0056H44.796175c.01771.000215.03543.00043.05314.00043.01771 0 .03543-.000215.05314-.00043h.0796l.0017-.0015c2.589329-.0707 4.686743-2.176859 4.908265-4.787585l.0013-.0013.0017-.03503c.0021-.02751.0041-.0548.0058-.0823.0041-.06576.0068-.1313.0079-.196412l1.83449-37.207738h-.0013c.0011-.0187.0015-.03761.0015-.05652"/>
                </svg>
                <span>+ Input Transaksi Shopee</span>
            </a>
        </div>

        <!-- Popularitas Paket -->
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs space-y-4">
            <h3 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">Distribusi Paket VPS</h3>

            <div class="space-y-3 text-xs">
                @foreach($specs as $spec)
                    <div>
                        <div class="flex justify-between items-center mb-1 font-medium">
                            <span class="text-slate-800">{{ $spec->name }}</span>
                            <span class="font-mono-code text-slate-500 font-semibold">{{ $spec->orders_count }} order</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-[#4A6FA5] h-2 rounded-full" style="width: {{ $specs->sum('orders_count') > 0 ? ($spec->orders_count / $specs->sum('orders_count')) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Open Support Tickets -->
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs space-y-3 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                    <h3 class="text-sm font-bold text-slate-900">Tiket Dukungan Masuk</h3>
                    <a href="{{ route('admin.tickets') }}" class="text-xs text-[#4A6FA5] font-semibold hover:underline">Semua &rarr;</a>
                </div>

                <div class="space-y-2 text-xs">
                    @forelse($open_tickets as $ticket)
                        <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="p-2.5 rounded-lg border border-slate-200 hover:bg-slate-50 block transition-colors">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-900 truncate max-w-[160px]">{{ $ticket->subject }}</span>
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold uppercase {{ $ticket->priority === 'high' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $ticket->priority }}
                                </span>
                            </div>
                            <span class="text-slate-500 text-[11px]">{{ $ticket->customer->full_name }} &bull; {{ $ticket->created_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p class="text-slate-400 text-center py-6">Tidak ada tiket terbuka.</p>
                    @endforelse
                </div>
            </div>

            <a href="{{ route('admin.tickets') }}" class="w-full py-2 px-3 rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium text-xs text-center block transition-colors">
                Buka Helpdesk Admin
            </a>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-xs">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-900 text-sm">Pesanan Terbaru</h3>
                <p class="text-xs text-slate-500">Daftar transaksi masuk dari Website dan Shopee</p>
            </div>
            <a href="{{ route('admin.orders') }}" class="text-xs font-semibold text-[#4A6FA5] hover:underline">
                Lihat Semua Antrean &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider border-b border-slate-200">
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
                <tbody class="divide-y divide-slate-100">
                    @foreach($recent_orders as $order)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-3.5 font-mono-code font-semibold text-slate-900">#ORD-{{ $order->id }}</td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-900 block">{{ $order->customer->full_name }}</span>
                                <span class="text-slate-400 font-mono-code text-[11px]">{{ $order->customer->email }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($order->channel === 'shopee')
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-orange-100 text-orange-800">Shopee</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800">Website</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $order->vpsSpec->name ?? 'Standard' }}</td>
                            <td class="px-5 py-3.5 text-slate-500 font-semibold">{{ $order->control_panel_label }}</td>
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase
                                    @if($order->status === 'active') bg-emerald-50 text-[#6ABD73] border border-emerald-200
                                    @elseif($order->status === 'pending') bg-amber-50 text-amber-700 border border-amber-200
                                    @elseif($order->status === 'cancelled') bg-rose-50 text-rose-700 border border-rose-200
                                    @else bg-blue-50 text-blue-700 border border-blue-200
                                    @endif">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">Rp {{ number_format($order->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.orders') }}" class="text-[#4A6FA5] font-semibold hover:underline">
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
