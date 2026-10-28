@extends('admin.reports.pdf.layout')

@section('pdf_content')
    <!-- KPI Summary -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">TOTAL TIKET MASUK</div>
                <div class="kpi-value">{{ $tickets->count() }}</div>
                <div class="kpi-sub">Rentang periode laporan</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">SELESAI (RESOLVED)</div>
                <div class="kpi-value">{{ $tickets->whereIn('status', ['resolved', 'closed'])->count() }}</div>
                <div class="kpi-sub">Masalah terselesaikan</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">DALAM PENANGANAN</div>
                <div class="kpi-value">{{ $tickets->whereIn('status', ['open', 'in_progress'])->count() }}</div>
                <div class="kpi-sub">Aktif ditangani tim teknis</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">RATA-RATA RESPON AWAL</div>
                <div class="kpi-value">{{ $avgFirstResponse ? round($avgFirstResponse / 60, 1) . ' Jam' : '0 Jam' }}</div>
                <div class="kpi-sub">First response SLA</div>
            </td>
        </tr>
    </table>

    <div class="section-header">DAFTAR TIKET BANTUAN TEKNIS & PERFORMA SLA</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">ID TIKET</th>
                <th style="width: 22%;">PELANGGAN</th>
                <th style="width: 32%;">SUBJEK LAPORAN</th>
                <th style="width: 10%;">PRIORITAS</th>
                <th style="width: 12%;" class="text-center">STATUS</th>
                <th style="width: 14%;">TGL DIBUAT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td class="font-mono"><strong>#TCK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $ticket->customer?->full_name ?? ($ticket->customer?->username ?? 'Pelanggan') }}</div>
                        <div style="font-size: 7px; color: #64748b;">{{ $ticket->customer?->email ?? '-' }}</div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #0f172a;">{{ $ticket->subject }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $ticket->priority === 'urgent' ? 'badge-danger' : ($ticket->priority === 'high' ? 'badge-warning' : 'badge-neutral') }}">
                            {{ strtoupper($ticket->priority ?? 'normal') }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ in_array($ticket->status, ['resolved', 'closed']) ? 'badge-neutral' : ($ticket->status === 'open' ? 'badge-danger' : 'badge-info') }}">
                            {{ strtoupper(str_replace('_', ' ', $ticket->status)) }}
                        </span>
                    </td>
                    <td>{{ $ticket->created_at ? $ticket->created_at->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center" style="padding: 16px; color: #94a3b8;">Tidak ada tiket pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if($tickets->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: 800;">
                    <td colspan="4" class="text-right" style="padding: 6px 7px; text-transform: uppercase;">TOTAL TIKET DUKUNGAN</td>
                    <td colspan="2" style="padding: 6px 7px;">{{ $tickets->count() }} Tiket Tercatat</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
