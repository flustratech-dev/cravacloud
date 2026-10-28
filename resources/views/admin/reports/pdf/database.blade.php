@extends('admin.reports.pdf.layout')

@section('pdf_content')
    <!-- KPI Summary Grid -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL OMZET MANAGED DB</div>
                <div class="kpi-value">Rp {{ number_format($dbRevenue, 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ $dbOrders->count() }} instance database terisolasi</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">SERVER DATABASE AKTIF</div>
                <div class="kpi-value">{{ $dbOrders->where('status', 'active')->count() }}</div>
                <div class="kpi-sub">Order database berstatus aktif</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">ENGINE DIDUKUNG</div>
                <div class="kpi-value">Postgres / MySQL</div>
                <div class="kpi-sub">+ Redis, MongoDB, Qdrant</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">KEAMANAN & ISOLASI</div>
                <div class="kpi-value">100% Dedicated</div>
                <div class="kpi-sub">Bebas bentrok web server</div>
            </td>
        </tr>
    </table>

    <!-- Section Heading -->
    <div class="section-header">DAFTAR RINCIAN INSTANCE MANAGED DATABASE SERVER</div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">NO. ORDER</th>
                <th style="width: 20%;">PELANGGAN</th>
                <th style="width: 20%;">PAKET DATABASE</th>
                <th style="width: 20%;">ENGINE & PROTOKOL</th>
                <th style="width: 18%;">SPESIFIKASI & MANAGER</th>
                <th style="width: 12%;">STATUS</th>
                <th style="width: 10%;" class="text-right">TOTAL (IDR)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dbOrders as $order)
                <tr>
                    <td class="font-mono">#ORD-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $order->customer?->full_name ?? ($order->customer?->username ?? 'Pelanggan') }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->customer?->email ?? '-' }}</div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $order->vpsSpec?->name ?? 'DB Micro Server' }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $order->vpsSpec?->tagline ?? 'Database terisolasi' }}</div>
                    </td>
                    <td>
                        <span class="badge badge-neutral font-mono" style="font-size: 7px; margin-bottom: 2px;">
                            {{ strtoupper($order->db_engine ?? 'POSTGRESQL 16') }}
                        </span>
                        <div style="font-size: 7px; color: #64748b;">Port: {{ $order->db_port ?? '5432' }} &bull; Static Public IP</div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #334155;">{{ $order->vpsSpec?->cpu ?? 2 }} vCPU &bull; {{ $order->vpsSpec?->ram ?? 2 }} GB RAM</div>
                        <div style="font-size: 7px; color: #64748b;">Manager: {{ ucfirst($order->db_manager ?? 'Direct Connection') }}</div>
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
                        Tidak ada transaksi pesanan Managed Database pada rentang periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($dbOrders->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 700;">
                    <td colspan="6" class="text-right" style="padding: 6px 8px; text-transform: uppercase;">
                        TOTAL AKUMULASI MANAGED DATABASE ({{ $dbOrders->count() }} UNIT)
                    </td>
                    <td class="text-right font-mono" style="padding: 6px 8px; color: #0f172a; font-size: 8.5px;">
                        Rp {{ number_format($dbOrders->sum('amount'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
