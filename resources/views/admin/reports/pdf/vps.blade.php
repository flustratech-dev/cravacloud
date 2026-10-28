@extends('admin.reports.pdf.layout')

@section('pdf_content')
    <!-- KPI Summary Grid -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL OMZET CLOUD VPS</div>
                <div class="kpi-value">Rp {{ number_format($vpsRevenue, 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ $vpsOrders->count() }} total pesanan compute</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">UNIT VPS AKTIF</div>
                <div class="kpi-value">{{ $vpsOrders->where('status', 'active')->count() }}</div>
                <div class="kpi-sub">Instance berjalan 24/7</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">RATA-RATA NILAI / VPS</div>
                <div class="kpi-value">Rp {{ number_format($vpsOrders->count() > 0 ? $vpsOrders->avg('amount') : 0, 0, ',', '.') }}</div>
                <div class="kpi-sub">ARPU per pesanan compute</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">STATUS OPERASIONAL</div>
                <div class="kpi-value">
                    {{ $vpsOrders->count() > 0 ? round(($vpsOrders->where('status', 'active')->count() / $vpsOrders->count()) * 100) : 100 }}%
                </div>
                <div class="kpi-sub">Tingkat kelancaran running</div>
            </td>
        </tr>
    </table>

    <!-- Section Heading -->
    <div class="section-header">DAFTAR RINCIAN PESANAN & INFRASTRUKTUR CLOUD VPS</div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">NO. ORDER</th>
                <th style="width: 20%;">PELANGGAN</th>
                <th style="width: 24%;">PAKET / RESOURCE</th>
                <th style="width: 16%;">OS & PANEL</th>
                <th style="width: 12%;">DATACENTER</th>
                <th style="width: 8%;" class="text-center">STATUS</th>
                <th style="width: 10%;" class="text-right">TOTAL (IDR)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vpsOrders as $order)
                <tr>
                    <td class="font-mono">#ORD-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $order->customer?->full_name ?? ($order->customer?->username ?? 'Pelanggan') }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->customer?->email ?? '-' }}</div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $order->vpsSpec?->name ?? 'Standard Cloud VPS' }}</div>
                        <div style="font-size: 7px; color: #64748b;">
                            {{ $order->vpsSpec?->cpu ?? 1 }} vCPU &bull; {{ $order->vpsSpec?->ram ?? 1 }} GB RAM &bull; {{ $order->vpsSpec?->disk ?? 20 }} GB NVMe
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600;">{{ $order->os_label ?? ($order->os ?? 'Ubuntu Server') }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->control_panel_label }}</div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #334155;">{{ strtoupper($order->datacenter_location ?? 'JAKARTA') }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->provider_label }}</div>
                    </td>
                    <td class="text-center">
                        @if($order->status === 'active')
                            <span class="badge badge-success">ACTIVE</span>
                        @elseif(in_array($order->status, ['pending', 'provisioning']))
                            <span class="badge badge-warning">{{ strtoupper($order->status) }}</span>
                        @else
                            <span class="badge badge-danger">{{ strtoupper($order->status) }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono" style="font-weight: 700; color: #0f172a;">
                        Rp {{ number_format($order->amount, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 16px; color: #94a3b8;">
                        Tidak ada transaksi pesanan Cloud VPS pada rentang periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($vpsOrders->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 700;">
                    <td colspan="6" class="text-right" style="padding: 6px 8px; text-transform: uppercase;">
                        TOTAL AKUMULASI CLOUD VPS ({{ $vpsOrders->count() }} UNIT)
                    </td>
                    <td class="text-right font-mono" style="padding: 6px 8px; color: #0f172a; font-size: 8.5px;">
                        Rp {{ number_format($vpsOrders->sum('amount'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
