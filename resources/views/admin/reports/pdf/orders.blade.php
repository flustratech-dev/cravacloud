@extends('admin.reports.pdf.layout')

@section('pdf_content')
    <!-- KPI Summary -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL PESANAN KONSOLIDASI</div>
                <div class="kpi-value">{{ $orders->count() }}</div>
                <div class="kpi-sub">Seluruh portofolio layanan</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">PESANAN AKTIF BERJALAN</div>
                <div class="kpi-value">{{ $orders->where('status', 'active')->count() }}</div>
                <div class="kpi-sub">Layanan running normal</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">DALAM PROSES / PROVISIONING</div>
                <div class="kpi-value">{{ $orders->whereIn('status', ['pending', 'provisioning'])->count() }}</div>
                <div class="kpi-sub">Antrean setup & verifikasi</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL NILAI TRANSAKSI</div>
                <div class="kpi-value">Rp {{ number_format($orders->sum('amount'), 0, ',', '.') }}</div>
                <div class="kpi-sub">Akumulasi seluruh tagihan order</div>
            </td>
        </tr>
    </table>

    <div class="section-header">DAFTAR KONSOLIDASI SELURUH PESANAN LAYANAN</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">NO. ORDER</th>
                <th style="width: 20%;">PELANGGAN</th>
                <th style="width: 16%;">KATEGORI LAYANAN</th>
                <th style="width: 20%;">PAKET / RESOURCE</th>
                <th style="width: 12%;">CHANNEL</th>
                <th style="width: 10%;" class="text-center">STATUS</th>
                <th style="width: 12%;" class="text-right">BIAYA (IDR)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                @php
                    $isAi = $order->isAiPackage();
                    $isDb = $order->isDatabasePackage();
                    $catLabel = $isAi ? 'AI AGENT' : ($isDb ? 'MANAGED DB' : 'CLOUD VPS');
                    $badgeClass = $isAi ? 'badge-info' : ($isDb ? 'badge-neutral' : 'badge-neutral');
                @endphp
                <tr>
                    <td class="font-mono">#ORD-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $order->customer?->full_name ?? ($order->customer?->username ?? 'Pelanggan') }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->customer?->email ?? '-' }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $catLabel }}</span>
                    </td>
                    <td>
                        <div style="font-weight: 600;">{{ $order->vpsSpec?->name ?? 'Standard Service' }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->control_panel_label }}</div>
                    </td>
                    <td style="font-size: 7px; color: #475569;">{{ strtoupper($order->channel ?? 'DIRECT') }}</td>
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
                <tr><td colspan="7" class="text-center" style="padding: 16px; color: #94a3b8;">Tidak ada data pesanan pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if($orders->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 800;">
                    <td colspan="6" class="text-right" style="padding: 6px 7px; text-transform: uppercase;">
                        TOTAL AKUMULASI SELURUH PESANAN ({{ $orders->count() }} DATA)
                    </td>
                    <td class="text-right font-mono" style="padding: 6px 7px; color: #0f172a; font-size: 8.5px;">
                        Rp {{ number_format($orders->sum('amount'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
