@extends('admin.reports.pdf.layout')

@section('pdf_content')
    <!-- Executive KPI Grid -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL OMZET LUNAS</div>
                <div class="kpi-value">Rp {{ number_format($revenue, 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ $paidInvoices }} transaksi lunas</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL PESANAN</div>
                <div class="kpi-value">{{ $orders->count() }}</div>
                <div class="kpi-sub">{{ $orders->where('status', 'active')->count() }} layanan aktif running</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">INVOICE PENAGIHAN</div>
                <div class="kpi-value">{{ $invoices->count() }}</div>
                <div class="kpi-sub">Rp {{ number_format($invoices->sum('amount'), 0, ',', '.') }} terbit</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">RATA-RATA SLA RESPON</div>
                <div class="kpi-value">{{ $avgFirstResponse ? round($avgFirstResponse / 60, 1) . ' Jam' : '0 Jam' }}</div>
                <div class="kpi-sub">{{ $tickets->count() }} total tiket bantuan</div>
            </td>
        </tr>
    </table>

    <!-- Ringkasan Portofolio Cloud VPS (Starter vs Enterprise Series) -->
    <div class="section-header">KINERJA PORTOFOLIO CLOUD VPS (STARTER &amp; ENTERPRISE SERIES)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 34%;">LINI PAKET CLOUD VPS</th>
                <th style="width: 18%;" class="text-center">TOTAL PESANAN</th>
                <th style="width: 20%;" class="text-center">AKTIF BEROPERASI</th>
                <th style="width: 28%;" class="text-right">TOTAL OMZET LUNAS</th>
            </tr>
        </thead>
        <tbody>
            @php
                $starterOrders = $vpsOrders->filter(fn ($o) => str_starts_with(strtolower((string) ($o->vpsSpec?->name ?? '')), 'starter'));
                $enterpriseOrders = $vpsOrders->filter(fn ($o) => str_starts_with(strtolower((string) ($o->vpsSpec?->name ?? '')), 'enterprise'));
                $otherOrders = $vpsOrders->reject(fn ($o) => $starterOrders->contains('id', $o->id) || $enterpriseOrders->contains('id', $o->id));
                $starterRevenue = (float) $invoices->where('status', 'paid')->filter(fn ($i) => $i->order && $starterOrders->contains('id', $i->order->id))->sum('amount');
                $enterpriseRevenue = (float) $invoices->where('status', 'paid')->filter(fn ($i) => $i->order && $enterpriseOrders->contains('id', $i->order->id))->sum('amount');
                $otherRevenue = max(0, (float) $revenue - $starterRevenue - $enterpriseRevenue);
            @endphp
            <tr>
                <td>
                    <div style="font-weight: 700; color: #0f172a;">1. Starter Series (XS / S / M / L)</div>
                    <div style="font-size: 7px; color: #64748b;">Cloud VPS KVM NVMe 2–4 vCPU, 1–8 GB RAM + 177 1-Click Stack</div>
                </td>
                <td class="text-center font-bold">{{ $starterOrders->count() }} unit</td>
                <td class="text-center">
                    <span class="badge badge-success">{{ $starterOrders->where('status', 'active')->count() }} running</span>
                </td>
                <td class="text-right font-mono" style="font-weight: 700; color: #0f172a;">
                    Rp {{ number_format($starterRevenue, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td>
                    <div style="font-weight: 700; color: #0f172a;">2. Enterprise Series (Pro / Max / Ultra / Extreme)</div>
                    <div style="font-size: 7px; color: #64748b;">High-Performance Compute 4–16 vCPU, 16–64 GB RAM + 1-Click Stack</div>
                </td>
                <td class="text-center font-bold">{{ $enterpriseOrders->count() }} unit</td>
                <td class="text-center">
                    <span class="badge badge-info">{{ $enterpriseOrders->where('status', 'active')->count() }} running</span>
                </td>
                <td class="text-right font-mono" style="font-weight: 700; color: #0f172a;">
                    Rp {{ number_format($enterpriseRevenue, 0, ',', '.') }}
                </td>
            </tr>
            @if($otherOrders->isNotEmpty())
                <tr>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">3. Paket Khusus / Custom</div>
                        <div style="font-size: 7px; color: #64748b;">Konfigurasi Cloud VPS kustom</div>
                    </td>
                    <td class="text-center font-bold">{{ $otherOrders->count() }} unit</td>
                    <td class="text-center">
                        <span class="badge badge-neutral">{{ $otherOrders->where('status', 'active')->count() }} running</span>
                    </td>
                    <td class="text-right font-mono" style="font-weight: 700; color: #0f172a;">
                        Rp {{ number_format($otherRevenue, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9; font-weight: 800;">
                <td style="padding: 6px 7px; text-transform: uppercase;">TOTAL KONSOLIDASI CLOUD VPS</td>
                <td class="text-center" style="padding: 6px 7px;">{{ $orders->count() }} unit</td>
                <td class="text-center" style="padding: 6px 7px;">{{ $orders->where('status', 'active')->count() }} aktif</td>
                <td class="text-right font-mono" style="padding: 6px 7px; color: #0f172a; font-size: 8.5px;">
                    Rp {{ number_format($revenue, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Breakdown Status Pesanan & Tiket -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div class="section-header">Distribusi Status Pesanan (Orders)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status Layanan</th>
                            <th class="text-right">Jumlah</th>
                            <th class="text-right">Rasio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalOrdersCount = max($orders->count(), 1); @endphp
                        @forelse($orderStatuses as $status => $count)
                            <tr>
                                <td>
                                    <span class="badge {{ in_array($status, ['active', 'completed']) ? 'badge-success' : (in_array($status, ['pending', 'provisioning']) ? 'badge-warning' : 'badge-danger') }}">
                                        {{ strtoupper(str_replace('_', ' ', $status)) }}
                                    </span>
                                </td>
                                <td class="text-right font-bold">{{ $count }}</td>
                                <td class="text-right">{{ round(($count / $totalOrdersCount) * 100, 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color: #94a3b8;">Tidak ada data pesanan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div class="section-header">Distribusi Status Tiket Support</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status Tiket</th>
                            <th class="text-right">Jumlah</th>
                            <th class="text-right">Rasio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalTicketsCount = max($tickets->count(), 1); @endphp
                        @forelse($ticketStatuses as $status => $count)
                            <tr>
                                <td>
                                    <span class="badge {{ in_array($status, ['resolved', 'closed']) ? 'badge-neutral' : (in_array($status, ['open']) ? 'badge-danger' : 'badge-info') }}">
                                        {{ strtoupper(str_replace('_', ' ', $status)) }}
                                    </span>
                                </td>
                                <td class="text-right font-bold">{{ $count }}</td>
                                <td class="text-right">{{ round(($count / $totalTicketsCount) * 100, 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color: #94a3b8;">Tidak ada data tiket pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- Rincian Tren Omzet Harian -->
    <div class="section-header">Rincian Omzet Harian (Paid Invoices)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40%;">TANGGAL TRANSAKSI</th>
                <th style="width: 30%;" class="text-center">INVOICE LUNAS</th>
                <th style="width: 30%;" class="text-right">TOTAL OMZET</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dailyRevenue as $date => $amount)
                @php
                    $count = $invoices->where('status', 'paid')->filter(fn($i) => $i->created_at->format('Y-m-d') === $date)->count();
                @endphp
                <tr>
                    <td style="font-weight: 600; color: #0f172a;">{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</td>
                    <td class="text-center">
                        <span class="badge badge-neutral font-mono">{{ $count }} transaksi</span>
                    </td>
                    <td class="text-right font-mono" style="font-weight: 700; color: #0f172a;">
                        Rp {{ number_format($amount, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="padding: 12px; color: #94a3b8;">
                        Tidak ada catatan transaksi lunas pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($dailyRevenue->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 800;">
                    <td colspan="2" class="text-right" style="padding: 6px 7px; text-transform: uppercase;">TOTAL OMZET KESELURUHAN</td>
                    <td class="text-right font-mono" style="padding: 6px 7px; color: #0f172a; font-size: 8.5px;">
                        Rp {{ number_format($revenue, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
