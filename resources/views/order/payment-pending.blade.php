@extends('layouts.app', ['title' => 'CravaCloud Menunggu Konfirmasi Pembayaran', 'robots' => 'noindex, follow'])

@section('content')
<div class="py-12 sm:py-16 bg-cream min-h-screen" x-data="paymentPending()">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="bg-white border-2 border-ink p-6 sm:p-10">
            {{-- Header --}}
            <div class="text-center border-b border-hairline pb-6 mb-6">
                <div class="w-16 h-16 bg-amber-100 border border-amber-500 flex items-center justify-center mx-auto mb-5">
                    <svg class="w-8 h-8 text-amber-700 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <span class="font-mono text-[10px] uppercase tracking-widest text-electric font-bold block mb-1">SINKRONISASI PAYMENT GATEWAY</span>
                <h1 class="font-display text-2xl sm:text-3xl font-bold text-ink tracking-tight mb-2">Menunggu Konfirmasi Pembayaran</h1>
                <p class="text-xs sm:text-sm text-muted max-w-md mx-auto">
                    Sistem sedang menunggu notifikasi webhook dari payment gateway. Halaman ini otomatis diperbarui setiap 5 detik.
                </p>
            </div>

            {{-- Order Info --}}
            <div class="bg-creamDark/50 border border-hairline p-5 space-y-3 text-xs mb-6 font-mono">
                <div class="flex justify-between gap-2">
                    <span class="text-muted uppercase">Invoice</span>
                    <span class="font-bold text-ink">
                        {{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}
                    </span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-muted uppercase">Paket</span>
                    <span class="font-display font-bold text-ink">{{ $order->vpsSpec->name }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-muted uppercase">Total Tagihan</span>
                    <span class="font-bold text-electric text-sm">
                        Rp {{ number_format($order->amount, 0, ',', '.') }}
                    </span>
                </div>
                <div class="flex justify-between items-center border-t border-hairline pt-3">
                    <span class="text-muted uppercase">Status</span>
                    <span class="inline-flex items-center gap-2 px-3 py-1 font-mono text-[11px] font-bold uppercase"
                          :class="statusClass">
                        <span class="w-2 h-2 rounded-full animate-pulse" :class="statusDot"></span>
                        <span x-text="statusLabel">Menunggu Pembayaran</span>
                    </span>
                </div>
            </div>

            {{-- Info block --}}
            <div class="bg-creamDark/40 border-l-4 border-electric p-4 text-xs mb-6">
                <p class="font-display font-bold text-ink mb-1">Status Pembayaran Otomatis</p>
                <p class="text-muted leading-relaxed">
                    Sistem secara berkala memverifikasi pembayaran Anda langsung ke payment gateway resmi Midtrans. Begitu pembayaran selesai di aplikasi Anda, halaman ini otomatis mengalihkan Anda ke halaman pesanan aktif.
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
                <button type="button" @click="poll(true)" :disabled="isChecking"
                        class="w-full sm:w-auto rounded-none px-6 py-3 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider text-center transition-colors flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                    <svg x-show="isChecking" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-text="isChecking ? 'Memeriksa ke Gateway...' : 'Cek Status Pembayaran Sekarang'"></span>
                </button>
                @if(!empty($snapToken))
                    <button type="button" @click="reopenPayment()"
                            class="w-full sm:w-auto rounded-none px-5 py-3 border border-electric text-electric hover:bg-electric hover:text-white font-mono font-bold text-xs uppercase tracking-wider text-center transition-colors cursor-pointer">
                        Buka Ulang Jendela Pembayaran
                    </button>
                @endif
                <a href="{{ route('dashboard.index') }}"
                   class="w-full sm:w-auto rounded-none px-5 py-3 border border-ink hover:bg-creamDark text-ink font-mono font-bold text-xs uppercase tracking-wider text-center transition-colors">
                    Lihat Dashboard
                </a>
            </div>

            <p class="text-center font-mono text-[11px] text-muted mt-6">
                Terakhir diperiksa: <span class="text-ink font-bold" x-text="lastChecked">--</span>
            </p>
        </div>
    </div>
</div>

<script>
function paymentPending() {
    return {
        pollUrl: '{{ route("order.payment.status.json", $order->id) }}',
        snapToken: '{{ $snapToken ?? "" }}',
        snapRedirectUrl: '{{ $snapRedirectUrl ?? "" }}',
        lastChecked: '--',
        currentStatus: '{{ $order->status }}',
        statusLabel: 'Menunggu Pembayaran',
        statusClass: 'bg-amber-100 text-amber-900 border border-amber-400',
        statusDot: 'bg-amber-600',
        isChecking: false,

        init() {
            this.poll();
            setInterval(() => this.poll(), 5000);
        },

        reopenPayment() {
            if (this.snapToken && window.snap) {
                window.snap.pay(this.snapToken, {
                    onSuccess: () => this.poll(true),
                    onPending: () => this.poll(true),
                    onError: () => this.poll(true),
                });
            } else if (this.snapRedirectUrl) {
                window.open(this.snapRedirectUrl, '_blank');
            }
        },

        async poll(isManual = false) {
            if (isManual) {
                this.isChecking = true;
            }
            try {
                const res = await fetch(this.pollUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const data = await res.json();

                this.currentStatus = data.status;
                this.updateBadge(data.status);
                this.lastChecked = new Date().toLocaleTimeString('id-ID');

                if (data.redirect_to) {
                    window.location.href = data.redirect_to;
                }
            } catch (err) {
                console.warn('Polling error:', err);
            } finally {
                if (isManual) {
                    setTimeout(() => { this.isChecking = false; }, 600);
                }
            }
        },

        updateBadge(status) {
            const map = {
                'pending': { l: 'Menunggu Pembayaran', c: 'bg-amber-100 text-amber-900 border border-amber-400', d: 'bg-amber-600' },
                'paid': { l: 'Lunas — Menyiapkan Server', c: 'bg-emerald-100 text-emerald-900 border border-emerald-500', d: 'bg-emerald-600' },
                'provisioning': { l: 'Sedang Provisioning', c: 'bg-electric text-white border border-electric', d: 'bg-white' },
                'active': { l: 'Aktif', c: 'bg-emerald-100 text-emerald-900 border border-emerald-500', d: 'bg-emerald-600' },
                'cancelled': { l: 'Dibatalkan', c: 'bg-red-100 text-red-900 border border-red-400', d: 'bg-red-600' },
                'expired': { l: 'Kadaluarsa', c: 'bg-creamDark text-muted border border-hairline', d: 'bg-muted' },
                'failed': { l: 'Gagal', c: 'bg-red-100 text-red-900 border border-red-400', d: 'bg-red-600' },
            };
            const m = map[status] || map['pending'];
            this.statusLabel = m.l;
            this.statusClass = m.c;
            this.statusDot = m.d;
        }
    };
}
</script>

@if(!empty($snapClientKey) && !empty($snapJsUrl))
    @push('scripts')
        <script src="{{ $snapJsUrl }}" data-client-key="{{ $snapClientKey }}"></script>
    @endpush
@endif
@endsection
