@extends('layouts.dashboard', ['title' => 'Tagihan & Invoice', 'headerTitle' => 'Tagihan & Invoice', 'backUrl' => route('dashboard.index'), 'backLabel' => 'Kembali ke Dashboard'])

@section('content')
<div class="space-y-6">
    {{-- Card Daftar Instance VPS & Masa Aktif Perpanjangan (Pilar 4) --}}
    @if(isset($instances) && $instances->isNotEmpty())
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="p-5 flex items-center justify-between border-b border-slate-100">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Server & Masa Aktif Perpanjangan</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola tanggal kedaluwarsa dan perpanjang server cloud Anda sebelum masa aktif habis.</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">{{ $instances->count() }} Server</span>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($instances as $instance)
                    <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}" class="font-bold text-slate-900 hover:text-black hover:underline">
                                    {{ $instance->hostname }}
                                </a>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono-code bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ $instance->public_ip ?? 'IP Pending' }}
                                </span>
                                @if($instance->status === 'running')
                                    <span class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>
                                @elseif($instance->status === 'suspended')
                                    <span class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold bg-amber-50 text-amber-700 border border-amber-200">Suspended</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold bg-slate-100 text-slate-700 border border-slate-300">{{ $instance->status }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>Paket: <strong class="text-slate-700">{{ $instance->renewal_spec->name ?? $instance->order?->vpsSpec?->name ?? 'Cloud VPS' }}</strong></span>
                                <span>&bull;</span>
                                <span>Jatuh Tempo: <strong class="text-slate-800">{{ $instance->expires_at ? $instance->expires_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : 'Belum ditentukan' }}</strong></span>
                                <span>&bull;</span>
                                <span>Tarif Perpanjangan: <strong class="text-slate-900 font-mono-code">Rp {{ number_format($instance->renewal_price, 0, ',', '.') }}/bln</strong></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($instance->canBeRenewed())
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span>Perpanjang</span>
                                </a>
                            @elseif($instance->isEolWithoutReplacement())
                                <a href="{{ route('dashboard.support') }}" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors">
                                    Migrasi Paket
                                </a>
                            @else
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors">
                                    Lihat Detail
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="p-5 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Layanan Berlangganan</h3>
                <p class="text-xs text-slate-500 mt-1">Kelola perpanjangan otomatis tanpa perlu menghubungi support.</p>
            </div>
            <span class="text-xs text-slate-500">{{ $subscriptions->count() }} layanan</span>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($subscriptions as $subscription)
                <div class="p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-900">{{ $subscription->vpsInstance->hostname ?? $subscription->vpsSpec->name ?? 'VPS' }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold border border-slate-300 bg-slate-50">{{ str_replace('_', ' ', $subscription->status) }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Periode berikutnya: {{ $subscription->next_billing_at?->timezone('Asia/Jakarta')->format('d M Y') ?? $subscription->current_period_end?->timezone('Asia/Jakarta')->format('d M Y') ?? '-' }} · Rp {{ number_format($subscription->unit_amount, 0, ',', '.') }}/{{ $subscription->billing_cycle === 'yearly' ? 'tahun' : 'bulan' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if($subscription->cancelled_at)
                            <form method="POST" action="{{ route('dashboard.subscriptions.resume', $subscription->id) }}">@csrf<button class="px-3 py-1.5 rounded bg-black text-white font-semibold">Lanjutkan</button></form>
                        @else
                            <form method="POST" action="{{ route('dashboard.subscriptions.auto-renew', $subscription->id) }}">@csrf<input type="hidden" name="auto_renew" value="{{ $subscription->auto_renew ? 0 : 1 }}"><button class="px-3 py-1.5 rounded border border-slate-300 font-semibold">{{ $subscription->auto_renew ? 'Matikan Auto-renew' : 'Aktifkan Auto-renew' }}</button></form>
                            @if($subscription->isCancellable())
                                <form method="POST" action="{{ route('dashboard.subscriptions.cancel', $subscription->id) }}" onsubmit="return confirm('Batalkan perpanjangan layanan ini?')">@csrf<button class="px-3 py-1.5 rounded border border-red-200 text-red-700 font-semibold">Batalkan</button></form>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-400">Belum ada langganan aktif.</div>
            @endforelse
        </div>
    </div>

    <!-- Top Summary (No line under title) -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-900">Langganan & Faktur Pembayaran</h2>
            <p class="text-xs text-slate-500 mt-1">
                Unduh atau cetak faktur untuk administrasi atau pembukuan Anda.
            </p>
        </div>
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border border-slate-300 bg-slate-50 text-slate-900">
                Status Akun: Aktif
            </span>
        </div>
    </div>

    <!-- Invoices Table (No line under title) -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="p-5 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-base">Riwayat Faktur</h3>
            <span class="text-xs text-slate-500">Total: {{ $invoices->total() }} Faktur</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3">Nomor Faktur</th>
                        <th class="px-5 py-3">Layanan</th>
                        <th class="px-5 py-3">Tanggal Terbit</th>
                        <th class="px-5 py-3">Jatuh Tempo</th>
                        <th class="px-5 py-3">Jumlah</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-900">{{ $invoice->order->vpsSpec->name ?? 'Cloud VPS' }}</span>
                                <span class="text-slate-400 block text-[11px]">Stack: {{ $invoice->order->control_panel_label }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ $invoice->issued_at ? $invoice->issued_at->timezone('Asia/Jakarta')->format('d M Y') : $invoice->created_at->timezone('Asia/Jakarta')->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ $invoice->due_at ? $invoice->due_at->timezone('Asia/Jakarta')->format('d M Y') : '-' }}
                            </td>
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">
                                Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-0.5 rounded text-xs font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                                    {{ $invoice->status === 'paid' ? 'Lunas' : ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('dashboard.invoice.print', $invoice->id) }}"
                                   @click.prevent="$dispatch('open-invoice-modal', { url: '{{ route('dashboard.invoice.print', $invoice->id) }}' })"
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded bg-black hover:bg-neutral-800 text-white text-xs font-semibold transition-colors cursor-pointer">
                                    Cetak
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-slate-400">
                                Belum ada faktur tagihan yang diterbitkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
