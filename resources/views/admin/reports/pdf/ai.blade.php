@extends('admin.reports.pdf.layout')

@section('pdf_content')
    <!-- KPI Summary Grid -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL OMZET AI AGENT</div>
                <div class="kpi-value">Rp {{ number_format($aiRevenue, 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ $aiOrders->count() }} workstation dideploy</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">AI WORKSTATION AKTIF</div>
                <div class="kpi-value">{{ $aiOrders->where('status', 'active')->count() }}</div>
                <div class="kpi-sub">Agent & container running</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">RESOURCE KOMPUTASI AI</div>
                <div class="kpi-value">{{ $aiOrders->sum(fn($o) => $o->vpsSpec?->cpu ?? 2) }} Core</div>
                <div class="kpi-sub">{{ $aiOrders->sum(fn($o) => $o->vpsSpec?->ram ?? 4) }} GB RAM dialokasikan</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">POPULARITAS STACK</div>
                <div class="kpi-value">Claude / Ollama</div>
                <div class="kpi-sub">Runtime AI terfavorit</div>
            </td>
        </tr>
    </table>

    <!-- Section Heading -->
    <div class="section-header">DAFTAR RINCIAN DEPLOYMENT AI AGENT & AUTONOMOUS WORKSTATION</div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">NO. ORDER</th>
                <th style="width: 20%;">PELANGGAN</th>
                <th style="width: 24%;">PAKET WORKSTATION AI</th>
                <th style="width: 20%;">AI RUNTIME & STACK</th>
                <th style="width: 16%;">SPESIFIKASI MESIN</th>
                <th style="width: 10%;">STATUS</th>
                <th style="width: 10%;" class="text-right">TOTAL (IDR)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($aiOrders as $order)
                <tr>
                    <td class="font-mono">#ORD-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $order->customer?->full_name ?? ($order->customer?->username ?? 'Pelanggan') }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->customer?->email ?? '-' }}</div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $order->vpsSpec?->name ?? 'AI Combo Workstation' }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->vpsSpec?->tagline ?? 'Server asisten AI siap pakai' }}</div>
                    </td>
                    <td>
                        <span class="badge badge-info" style="font-size: 7px; margin-bottom: 2px;">
                            {{ $order->control_panel_label }}
                        </span>
                        <div style="font-size: 7px; color: #64748b;">Zero Setup Pre-configured</div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #334155;">{{ $order->vpsSpec?->cpu ?? 2 }} vCPU &bull; {{ $order->vpsSpec?->ram ?? 4 }} GB RAM</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->vpsSpec?->disk ?? 60 }} GB NVMe High Speed</div>
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
                        Tidak ada transaksi pesanan AI Agent & Workstation pada rentang periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($aiOrders->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 700;">
                    <td colspan="6" class="text-right" style="padding: 6px 8px; text-transform: uppercase;">
                        TOTAL AKUMULASI AI AGENT ({{ $aiOrders->count() }} UNIT)
                    </td>
                    <td class="text-right font-mono" style="padding: 6px 8px; color: #0f172a; font-size: 8.5px;">
                        Rp {{ number_format($aiOrders->sum('amount'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
