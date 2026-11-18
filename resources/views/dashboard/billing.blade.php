@extends('layouts.dashboard', ['title' => 'Tagihan & Invoice', 'headerTitle' => 'Tagihan & Invoice'])

@section('content')
<div class="space-y-6">
    {{-- Instance Active Periods & Renewal --}}
    @if(isset($instances) && $instances->isNotEmpty())
        <div class="bg-white border border-hairline overflow-hidden">
            <div class="p-5 flex items-center justify-between border-b border-hairline bg-creamDark/40">
                <div>
                    <span class="font-mono text-[10px] uppercase tracking-widest text-muted block">PERPANJANGAN LAYANAN</span>
                    <h3 class="font-display font-bold text-ink text-base">Server &amp; Masa Aktif Perpanjangan</h3>
                    <p class="text-xs text-muted mt-0.5">Kelola tanggal kedaluwarsa dan perpanjang server cloud sebelum masa aktif berakhir.</p>
                </div>
                <span class="font-mono text-xs font-bold px-3 py-1 bg-white border border-hairline text-ink">{{ $instances->count() }} Server</span>
            </div>
            <div class="divide-y divide-hairline">
                @foreach($instances as $instance)
                    <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-creamDark/20 transition-colors">
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}" class="font-display font-bold text-ink hover:text-electric hover:underline">
                                    {{ $instance->hostname }}
                                </a>
                                <span class="px-2 py-0.5 font-mono text-[10px] bg-creamDark border border-hairline text-ink">
                                    {{ $instance->public_ip ?? 'IP Pending' }}
                                </span>
                                @if($instance->status === 'running')
                                    <span class="px-2 py-0.5 font-mono text-[10px] uppercase font-bold bg-emerald-100 text-emerald-900 border border-emerald-400">Aktif</span>
                                @elseif($instance->status === 'suspended')
                                    <span class="px-2 py-0.5 font-mono text-[10px] uppercase font-bold bg-amber-100 text-amber-900 border border-amber-400">Suspended</span>
                                @else
                                    <span class="px-2 py-0.5 font-mono text-[10px] uppercase font-bold bg-creamDark text-ink border border-hairline">{{ $instance->status }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-muted flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>Paket: <strong class="text-ink">{{ $instance->renewal_spec->name ?? $instance->order?->vpsSpec?->name ?? 'Cloud VPS' }}</strong></span>
                                <span>·</span>
                                <span>Jatuh Tempo: <strong class="text-ink">{{ $instance->expires_at ? $instance->expires_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : 'Belum ditentukan' }}</strong></span>
                                <span>·</span>
                                <span>Tarif: <strong class="text-ink font-mono">Rp {{ number_format($instance->renewal_price, 0, ',', '.') }}/bln</strong></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($instance->canBeRenewed())
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}" class="rounded-none px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5">
                                    <span>Perpanjang</span>
                                </a>
                            @elseif($instance->isEolWithoutReplacement())
                                <a href="{{ route('dashboard.support') }}" class="rounded-none px-4 py-2 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                                    Migrasi Paket
                                </a>
                            @else
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}" class="rounded-none px-4 py-2 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                                    Lihat Detail
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Subscriptions --}}
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="p-5 flex items-center justify-between border-b border-hairline">
            <div>
                <h3 class="font-display font-bold text-ink text-base">Layanan Berlangganan</h3>
                <p class="text-xs text-muted mt-0.5">Kelola perpanjangan otomatis tanpa perlu menghubungi support.</p>
            </div>
            <span class="font-mono text-xs text-muted">{{ $subscriptions->count() }} layanan</span>
        </div>
        <div class="divide-y divide-hairline">
            @forelse($subscriptions as $subscription)
                <div class="p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-display font-bold text-ink">{{ $subscription->vpsInstance->hostname ?? $subscription->vpsSpec->name ?? 'VPS' }}</span>
                            <span class="px-2 py-0.5 font-mono text-[10px] uppercase font-bold border border-hairline bg-creamDark text-ink">{{ str_replace('_', ' ', $subscription->status) }}</span>
                        </div>
                        <p class="text-xs text-muted mt-1 font-mono">Periode berikutnya: {{ $subscription->next_billing_at?->timezone('Asia/Jakarta')->format('d M Y') ?? $subscription->current_period_end?->timezone('Asia/Jakarta')->format('d M Y') ?? '-' }} · Rp {{ number_format($subscription->unit_amount, 0, ',', '.') }}/{{ $subscription->billing_cycle === 'yearly' ? 'tahun' : 'bulan' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if($subscription->cancelled_at)
                            <form method="POST" action="{{ route('dashboard.subscriptions.resume', $subscription->id) }}">@csrf<button class="rounded-none px-3.5 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase cursor-pointer">Lanjutkan</button></form>
                        @else
                            <form method="POST" action="{{ route('dashboard.subscriptions.auto-renew', $subscription->id) }}">@csrf<input type="hidden" name="auto_renew" value="{{ $subscription->auto_renew ? 0 : 1 }}"><button class="rounded-none px-3.5 py-2 border border-ink hover:bg-creamDark font-mono text-xs font-bold uppercase cursor-pointer">{{ $subscription->auto_renew ? 'Matikan Auto-renew' : 'Aktifkan Auto-renew' }}</button></form>
                            @if($subscription->isCancellable())
                                <form method="POST" action="{{ route('dashboard.subscriptions.cancel', $subscription->id) }}" onsubmit="return confirm('Batalkan perpanjangan layanan ini?')">@csrf<button class="rounded-none px-3.5 py-2 border border-red-400 text-red-700 hover:bg-red-50 font-mono text-xs font-bold uppercase cursor-pointer">Batalkan</button></form>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center font-mono text-xs text-muted">Belum ada langganan aktif.</div>
            @endforelse
        </div>
    </div>

    {{-- Top Billing Banner --}}
    <div class="bg-white border border-hairline p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display text-base font-bold text-ink">Langganan &amp; Faktur Pembayaran</h2>
            <p class="text-xs text-muted mt-1">Unduh atau cetak faktur PDF resmi untuk administrasi atau pembukuan Anda.</p>
        </div>
        <span class="inline-flex items-center gap-2 px-3 py-1.5 font-mono text-xs font-bold uppercase border border-emerald-500 bg-emerald-50 text-emerald-900">
            <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
            Status Akun: Aktif
        </span>
    </div>

    {{-- Invoices Table --}}
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="p-5 border-b border-hairline flex items-center justify-between">
            <h3 class="font-display font-bold text-ink text-base">Riwayat Faktur</h3>
            <span class="font-mono text-xs text-muted">Total: {{ $invoices->total() }} Faktur</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark border-b border-hairline font-mono text-ink font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Nomor Faktur</th>
                        <th class="px-5 py-3.5">Layanan</th>
                        <th class="px-5 py-3.5">Tanggal Terbit</th>
                        <th class="px-5 py-3.5">Jatuh Tempo</th>
                        <th class="px-5 py-3.5">Jumlah</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5 font-mono font-bold text-ink whitespace-nowrap">{{ $invoice->invoice_number }}</td>
                            <td class="px-5 py-3.5">
                                <span class="font-display font-bold text-ink">{{ $invoice->order->vpsSpec->name ?? 'Cloud VPS' }}</span>
                                <span class="text-muted block text-[11px]">Stack: {{ $invoice->order->control_panel_label }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-muted whitespace-nowrap">
                                {{ $invoice->issued_at ? $invoice->issued_at->timezone('Asia/Jakarta')->format('d M Y') : $invoice->created_at->timezone('Asia/Jakarta')->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-muted whitespace-nowrap">
                                {{ $invoice->due_at ? $invoice->due_at->timezone('Asia/Jakarta')->format('d M Y') : '-' }}
                            </td>
                            <td class="px-5 py-3.5 font-mono font-bold text-ink whitespace-nowrap">
                                Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="px-2.5 py-0.5 font-mono text-[11px] font-bold uppercase border {{ $invoice->status === 'paid' ? 'border-emerald-500 bg-emerald-50 text-emerald-900' : 'border-hairline bg-creamDark text-ink' }}">
                                    {{ $invoice->status === 'paid' ? 'Lunas' : ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <a href="{{ route('dashboard.invoice.print', $invoice->id) }}"
                                   @click.prevent="$dispatch('open-invoice-modal', { url: '{{ route('dashboard.invoice.print', $invoice->id) }}' })"
                                   class="rounded-none inline-flex items-center px-3 py-1.5 bg-ink hover:bg-electric text-white font-mono text-[11px] font-bold uppercase tracking-wider transition-colors cursor-pointer">
                                    Cetak PDF
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada faktur tagihan yang diterbitkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="p-4 border-t border-hairline">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
