@extends('admin.reports.pdf.layout')

@section('pdf_content')
    <!-- KPI Summary -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL TAGIHAN TERBIT</div>
                <div class="kpi-value">{{ $invoices->count() }}</div>
                <div class="kpi-sub">Rp {{ number_format($invoices->sum('amount'), 0, ',', '.') }} total nilai</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">INVOICE LUNAS (PAID)</div>
                <div class="kpi-value">{{ $invoices->where('status', 'paid')->count() }}</div>
                <div class="kpi-sub">Rp {{ number_format($invoices->where('status', 'paid')->sum('amount'), 0, ',', '.') }} omzet terkumpul</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">MENUNGGU PEMBAYARAN</div>
                <div class="kpi-value">{{ $invoices->where('status', 'unpaid')->count() }}</div>
                <div class="kpi-sub">Rp {{ number_format($invoices->where('status', 'unpaid')->sum('amount'), 0, ',', '.') }} piutang</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TINGKAT PELUNASAN</div>
                <div class="kpi-value">
                    {{ $invoices->count() > 0 ? round(($invoices->where('status', 'paid')->count() / $invoices->count()) * 100) : 0 }}%
                </div>
                <div class="kpi-sub">Rasio penagihan sukses</div>
            </td>
        </tr>
    </table>

    <div class="section-header">DAFTAR RINCIAN FAKTUR PENAGIHAN & ARUS KAS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 18%;">NOMOR INVOICE</th>
                <th style="width: 12%;">NO. ORDER</th>
                <th style="width: 14%;">STATUS</th>
                <th style="width: 16%;" class="text-right">NOMINAL (IDR)</th>
                <th style="width: 13%;">TGL TERBIT</th>
                <th style="width: 13%;">JATUH TEMPO</th>
                <th style="width: 14%;">TGL LUNAS</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td class="font-mono"><strong>{{ $invoice->invoice_number ?? ('INV-'.$invoice->id) }}</strong></td>
                    <td class="font-mono">{{ $invoice->order_id ? '#ORD-'.str_pad($invoice->order_id, 5, '0', STR_PAD_LEFT) : '-' }}</td>
                    <td>
                        <span class="badge {{ $invoice->status === 'paid' ? 'badge-success' : ($invoice->status === 'unpaid' ? 'badge-warning' : 'badge-danger') }}">
                            {{ strtoupper($invoice->status) }}
                        </span>
                    </td>
                    <td class="text-right font-mono" style="font-weight: 700; color: #0f172a;">
                        Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                    </td>
                    <td>{{ $invoice->issued_at ? $invoice->issued_at->timezone('Asia/Jakarta')->translatedFormat('d M Y') : ($invoice->created_at ? $invoice->created_at->timezone('Asia/Jakarta')->translatedFormat('d M Y') : '-') }}</td>
                    <td>{{ $invoice->due_at ? $invoice->due_at->timezone('Asia/Jakarta')->translatedFormat('d M Y') : '-' }}</td>
                    <td>{{ $invoice->paid_at ? $invoice->paid_at->timezone('Asia/Jakarta')->translatedFormat('d M Y') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center" style="padding: 16px; color: #94a3b8;">Tidak ada data invoice pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if($invoices->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 800;">
                    <td colspan="3" class="text-right" style="padding: 6px 7px; text-transform: uppercase;">TOTAL TAGIHAN TERBIT</td>
                    <td class="text-right font-mono" style="padding: 6px 7px; color: #0f172a; font-size: 8.5px;">
                        Rp {{ number_format($invoices->sum('amount'), 0, ',', '.') }}
                    </td>
                    <td colspan="3" style="padding: 6px 7px;">{{ $invoices->where('status', 'paid')->count() }} Lunas dari {{ $invoices->count() }} Invoice</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
