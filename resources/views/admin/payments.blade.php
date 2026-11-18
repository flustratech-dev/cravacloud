@extends('layouts.admin', ['title' => 'Verifikasi Pembayaran', 'headerTitle' => 'Verifikasi Pembayaran Manual', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    approveModal: false,
    rejectModal: false,
    holdModal: false,
    selectedOrder: null,
    reference: '',
    note: '',
    rejectReason: '',
    copiedAmount: false,

    openApprove(order) {
        this.selectedOrder = order;
        this.reference = '';
        this.note = 'Mutasi DANA Bisnis cocok';
        this.approveModal = true;
    },

    openHold(order) {
        this.selectedOrder = order;
        this.note = 'Mutasi belum tampak di DANA Bisnis MERCHANT, menunggu pengecekan berkala';
        this.holdModal = true;
    },

    openReject(order) {
        this.selectedOrder = order;
        this.rejectReason = 'Mutasi pembayaran tidak ditemukan pada akun DANA Bisnis MERCHANT';
        this.rejectModal = true;
    }
}">
    {{-- Banner Petunjuk Verifikasi DANA Bisnis --}}
    <div class="bg-white border border-hairline p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 bg-ink text-cream flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-display text-sm font-bold text-ink">Instruksi Verifikasi Mutasi DANA Bisnis</h2>
                <p class="text-xs text-muted mt-0.5 leading-relaxed">
                    Buka aplikasi DANA Bisnis pada merchant <strong class="text-ink">MERCHANT</strong> &rarr; menu <strong class="text-ink">Riwayat Transaksi</strong>.
                    Cocokkan nominal persis sebelum menyetujui pesanan. Sistem otomatis mengirimkan notifikasi email resmi ke pelanggan setelah disetujui atau ditolak.
                </p>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-2.5">
            <div class="px-3.5 py-2 bg-creamDark/60 border border-hairline text-xs">
                <span class="font-mono text-muted block text-[10px] uppercase tracking-wider">Merchant DANA</span>
                <strong class="text-ink font-mono font-bold">MERCHANT</strong>
            </div>
            <div class="px-3.5 py-2 bg-white border border-ink text-xs">
                <span class="font-mono text-muted block text-[10px] uppercase tracking-wider">Perlu Verifikasi</span>
                <strong class="text-electric font-mono font-bold">{{ $pendingCount }} Pesanan</strong>
            </div>
        </div>
    </div>

    {{-- Filter Tabs & Search Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-1.5 font-mono text-xs pb-1 sm:pb-0">
            <a href="{{ route('admin.payments', ['status' => 'pending']) }}"
               class="px-3.5 py-2 transition-colors whitespace-nowrap flex items-center gap-1.5 {{ $status === 'pending' ? 'bg-electric text-white font-bold' : 'bg-white text-ink border border-hairline hover:border-ink' }}">
                <span>Menunggu Verifikasi</span>
                @if($pendingCount > 0)
                    <span class="px-1.5 py-0.5 text-[10px] font-bold {{ $status === 'pending' ? 'bg-white text-electric' : 'bg-ink text-cream' }}">
                        {{ $pendingCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.payments', ['status' => 'paid']) }}"
               class="px-3.5 py-2 transition-colors whitespace-nowrap {{ $status === 'paid' ? 'bg-electric text-white font-bold' : 'bg-white text-ink border border-hairline hover:border-ink' }}">
                Telah Disetujui (Paid)
            </a>

            <a href="{{ route('admin.payments', ['status' => 'cancelled']) }}"
               class="px-3.5 py-2 transition-colors whitespace-nowrap {{ $status === 'cancelled' ? 'bg-electric text-white font-bold' : 'bg-white text-ink border border-hairline hover:border-ink' }}">
                Ditolak / Batal
            </a>

            <a href="{{ route('admin.payments', ['status' => 'all']) }}"
               class="px-3.5 py-2 transition-colors whitespace-nowrap {{ $status === 'all' ? 'bg-electric text-white font-bold' : 'bg-white text-ink border border-hairline hover:border-ink' }}">
                Semua Riwayat
            </a>
        </div>

        <form method="GET" action="{{ route('admin.payments') }}" class="flex items-center gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="relative w-full sm:w-64">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Cari invoice / pelanggan / ID..."
                       class="w-full text-xs pl-8 pr-3 py-2 border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                <svg class="w-4 h-4 text-muted absolute left-2.5 top-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button type="submit" class="px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                Cari
            </button>
        </form>
    </div>

    {{-- Tabel Order & Pembayaran --}}
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Invoice &amp; ID</th>
                        <th class="px-5 py-3.5">Pelanggan</th>
                        <th class="px-5 py-3.5">Paket VPS</th>
                        <th class="px-5 py-3.5">Nominal Mutasi</th>
                        <th class="px-5 py-3.5">Waktu Order</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi Verifikasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($orders as $order)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-mono font-bold text-ink text-xs">
                                    {{ $order->invoice->invoice_number ?? ('INV-' . $order->id) }}
                                </div>
                                <div class="font-mono text-[11px] text-muted mt-0.5">
                                    Order #{{ $order->id }} &middot; {{ strtoupper($order->payment_method ?? 'QRIS') }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="font-bold text-ink">{{ $order->customer->full_name ?? 'Pelanggan' }}</div>
                                <div class="text-[11px] text-muted font-mono mt-0.5">{{ $order->customer->email ?? '-' }}</div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="font-semibold text-ink">{{ $order->vpsSpec->name ?? 'VPS' }}</div>
                                <div class="text-[11px] text-muted font-mono mt-0.5">{{ $order->hostname ?? '-' }}</div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-ink text-sm">
                                        Rp {{ number_format($order->amount, 0, ',', '.') }}
                                    </span>
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ $order->amount }}'); if (typeof showAlert === 'function') showAlert('Nominal disalin: {{ $order->amount }}')"
                                            class="p-1 text-muted hover:text-ink cursor-pointer" title="Salin Nominal">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="font-mono text-[10px] text-muted mt-0.5">DANA: MERCHANT</div>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="text-ink font-mono">{{ $order->created_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }}</div>
                                <div class="text-[11px] text-muted mt-0.5">{{ $order->created_at->diffForHumans() }}</div>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                @if($order->status === 'paid')
                                    <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold bg-electric text-white inline-flex items-center">
                                        LUNAS (PAID)
                                    </span>
                                @elseif($order->status === 'cancelled')
                                    <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold bg-ink text-cream inline-flex items-center">
                                        DITOLAK
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold bg-creamDark text-ink border border-ink inline-flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 bg-electric animate-pulse"></span>
                                        MENUNGGU MUTASI
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                @if($order->status === 'pending')
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button"
                                                @click="openApprove({{ Js::from($order) }})"
                                                class="px-3 py-1.5 bg-electric hover:bg-electricHover text-white font-mono font-bold text-[10px] uppercase tracking-wider transition-colors inline-flex items-center gap-1 cursor-pointer">
                                            <span>Setujui</span>
                                        </button>

                                        <button type="button"
                                                @click="openHold({{ Js::from($order) }})"
                                                class="px-2.5 py-1.5 bg-white border border-hairline hover:border-ink text-ink font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer"
                                                title="Tahan & Catat Keterangan">
                                            <span>Tunda</span>
                                        </button>

                                        <button type="button"
                                                @click="openReject({{ Js::from($order) }})"
                                                class="px-2.5 py-1.5 bg-white border border-ink hover:bg-ink hover:text-white text-ink font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer"
                                                title="Tolak Pembayaran">
                                            <span>Tolak</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="text-muted font-mono text-[11px]">
                                        {{ $order->status === 'paid' ? 'Telah diverifikasi' : 'Telah dibatalkan' }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-muted">
                                <div class="w-12 h-12 bg-creamDark border border-hairline flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-ink" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                </div>
                                <p class="font-display text-sm font-bold text-ink">Tidak ada transaksi yang perlu diverifikasi</p>
                                <p class="font-mono text-xs text-muted mt-1">Semua pesanan pada filter ini sudah diproses.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-hairline">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL 1: SETUJUI PEMBAYARAN --}}
    <div x-show="approveModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-ink/50 transition-opacity" @click="approveModal = false"></div>

            <div class="relative bg-white text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-ink">
                <form :action="'/admin/payments/' + (selectedOrder ? selectedOrder.id : '') + '/approve'" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-hairline mb-4">
                            <h3 class="font-display text-base font-bold text-ink">Setujui Pembayaran Order</h3>
                            <button type="button" @click="approveModal = false" class="text-muted hover:text-ink font-mono text-base cursor-pointer">&times;</button>
                        </div>

                        <template x-if="selectedOrder">
                            <div class="space-y-4 text-xs">
                                <div class="p-3.5 bg-creamDark/40 border border-hairline space-y-1.5">
                                    <div class="flex justify-between">
                                        <span class="text-muted">Order &amp; Invoice:</span>
                                        <span class="font-mono font-bold text-ink" x-text="'Order #' + selectedOrder.id + ' (' + (selectedOrder.invoice ? selectedOrder.invoice.invoice_number : '-') + ')'"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted">Pelanggan:</span>
                                        <span class="font-semibold text-ink" x-text="selectedOrder.customer ? selectedOrder.customer.full_name : '-'"></span>
                                    </div>
                                    <div class="flex justify-between items-center border-t border-hairline pt-1.5">
                                        <span class="text-ink font-semibold">Nominal yang Harus Dicocokkan:</span>
                                        <span class="font-mono font-bold text-sm text-electric" x-text="'Rp ' + Number(selectedOrder.amount).toLocaleString('id-ID')"></span>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted block">Nomor Referensi Mutasi DANA (Opsional):</label>
                                    <input type="text" name="reference" x-model="reference" placeholder="Contoh: DANA-20260915-XXXXX"
                                           class="w-full text-xs p-2.5 border border-hairline bg-white font-mono focus:outline-none focus:border-electric">
                                </div>

                                <div class="space-y-1.5">
                                    <label class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted block">Catatan Tambahan (Opsional):</label>
                                    <input type="text" name="note" x-model="note" placeholder="Catatan internal verifikasi admin"
                                           class="w-full text-xs p-2.5 border border-hairline bg-white focus:outline-none focus:border-electric">
                                </div>

                                <div class="p-3 bg-white border border-electric text-ink text-[11px] leading-relaxed">
                                    <strong>Perhatian:</strong> Menyetujui order ini akan mengubah status menjadi <strong>PAID</strong>, menandai invoice sebagai <strong>LUNAS</strong>, dan otomatis mengirim email konfirmasi pembayaran resmi ke pelanggan.
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="bg-creamDark/40 px-6 py-3.5 border-t border-hairline flex items-center justify-end gap-2.5">
                        <button type="button" @click="approveModal = false"
                                class="px-4 py-2 border border-hairline bg-white hover:border-ink font-mono text-xs font-bold uppercase text-ink cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-electric hover:bg-electricHover font-mono text-xs font-bold uppercase tracking-wider text-white transition-colors cursor-pointer">
                            Ya, Setujui &amp; Kirim Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL 2: TAHAN / PENDING CATATAN --}}
    <div x-show="holdModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-ink/50 transition-opacity" @click="holdModal = false"></div>

            <div class="relative bg-white text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md sm:w-full border border-ink">
                <form :action="'/admin/payments/' + (selectedOrder ? selectedOrder.id : '') + '/hold'" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-hairline mb-4">
                            <h3 class="font-display text-base font-bold text-ink">Tunda / Catat Status Mutasi</h3>
                            <button type="button" @click="holdModal = false" class="text-muted hover:text-ink font-mono text-base cursor-pointer">&times;</button>
                        </div>

                        <div class="space-y-4 text-xs">
                            <p class="text-muted leading-relaxed">
                                Order akan tetap berada pada status <strong class="text-ink">Pending</strong>. Catatan ini disimpan pada riwayat audit untuk pemantauan tim admin.
                            </p>

                            <div class="space-y-1.5">
                                <label class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted block">Keterangan Penundaan:</label>
                                <textarea name="note" x-model="note" rows="3" required
                                          class="w-full text-xs p-2.5 border border-hairline bg-white focus:outline-none focus:border-electric"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-creamDark/40 px-6 py-3.5 border-t border-hairline flex items-center justify-end gap-2.5">
                        <button type="button" @click="holdModal = false"
                                class="px-4 py-2 border border-hairline bg-white hover:border-ink font-mono text-xs font-bold uppercase text-ink cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-electric hover:bg-electricHover font-mono text-xs font-bold uppercase tracking-wider text-white transition-colors cursor-pointer">
                            Simpan Catatan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL 3: TOLAK PEMBAYARAN --}}
    <div x-show="rejectModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-ink/50 transition-opacity" @click="rejectModal = false"></div>

            <div class="relative bg-white text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-ink">
                <form :action="'/admin/payments/' + (selectedOrder ? selectedOrder.id : '') + '/reject'" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-hairline mb-4">
                            <h3 class="font-display text-base font-bold text-ink">Tolak Pembayaran Order</h3>
                            <button type="button" @click="rejectModal = false" class="text-muted hover:text-ink font-mono text-base cursor-pointer">&times;</button>
                        </div>

                        <div class="space-y-4 text-xs">
                            <p class="text-muted leading-relaxed">
                                Tindakan ini akan membatalkan pesanan (status <strong class="text-ink">CANCELLED</strong>) dan mengirimkan notifikasi email penolakan resmi ke pelanggan.
                            </p>

                            <div class="space-y-1.5">
                                <label class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted block">Pilihan Alasan Cepat:</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" @click="rejectReason = 'Mutasi pembayaran tidak ditemukan pada akun DANA Bisnis MERCHANT'"
                                            class="px-2.5 py-1 bg-creamDark hover:border-ink text-ink font-mono text-[10px] border border-hairline cursor-pointer">
                                        Mutasi Tidak Ditemukan
                                    </button>
                                    <button type="button" @click="rejectReason = 'Nominal pembayaran tidak sesuai dengan tagihan invoice'"
                                            class="px-2.5 py-1 bg-creamDark hover:border-ink text-ink font-mono text-[10px] border border-hairline cursor-pointer">
                                        Nominal Tidak Sesuai
                                    </button>
                                    <button type="button" @click="rejectReason = 'Batas waktu pembayaran telah kedaluwarsa'"
                                            class="px-2.5 py-1 bg-creamDark hover:border-ink text-ink font-mono text-[10px] border border-hairline cursor-pointer">
                                        Waktu Kedaluwarsa
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted block">Alasan Penolakan (Akan tercantum di email pelanggan):</label>
                                <textarea name="reason" x-model="rejectReason" rows="3" required
                                          class="w-full text-xs p-2.5 border border-hairline bg-white focus:outline-none focus:border-ink"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-creamDark/40 px-6 py-3.5 border-t border-hairline flex items-center justify-end gap-2.5">
                        <button type="button" @click="rejectModal = false"
                                class="px-4 py-2 border border-hairline bg-white hover:border-ink font-mono text-xs font-bold uppercase text-ink cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-ink hover:bg-electric font-mono text-xs font-bold uppercase tracking-wider text-white transition-colors cursor-pointer">
                            Ya, Tolak &amp; Batalkan Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection