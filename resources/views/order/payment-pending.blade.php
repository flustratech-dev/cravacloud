@extends('layouts.app', ['title' => 'Menunggu Konfirmasi Pembayaran — CravaCloud'])

@section('content')
<div class="py-12 bg-slate-50 min-h-screen" x-data="paymentPending()">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-8">
            {{-- Header --}}
            <div class="text-center mb-8">
                <div class="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-amber-600 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mb-2">Menunggu Konfirmasi Pembayaran</h1>
                <p class="text-sm text-slate-600">
                    Sistem sedang menunggu notifikasi dari payment gateway.
                    Halaman ini akan otomatis diperbarui setiap 5 detik.
                </p>
            </div>

            {{-- Order info --}}
            <div class="bg-slate-50 rounded-lg p-4 space-y-3 text-sm mb-6">
                <div class="flex justify-between">
                    <span class="text-slate-500">Invoice</span>
                    <span class="font-mono-code font-semibold text-slate-900">
                        {{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Paket</span>
                    <span class="font-semibold text-slate-900">{{ $order->vpsSpec->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Total Tagihan</span>
                    <span class="font-bold text-slate-900 font-mono-code">
                        Rp {{ number_format($order->amount, 0, ',', '.') }}
                    </span>
                </div>
                <div class="flex justify-between items-center border-t border-slate-200 pt-3">
                    <span class="text-slate-500">Status</span>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold"
                          :class="statusClass">
                        <span class="w-2 h-2 rounded-full animate-pulse" :class="statusDot"></span>
                        <span x-text="statusLabel">Menunggu Pembayaran</span>
                    </span>
                </div>
            </div>

            {{-- Info block --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800 mb-6">
                <p class="font-semibold mb-1">Status Pembayaran Otomatis:</p>
                <p class="text-blue-700 text-xs leading-relaxed">
                    Sistem secara berkala memverifikasi pembayaran Anda langsung ke payment gateway resmi Midtrans. Begitu pembayaran selesai di aplikasi Anda, halaman ini akan otomatis dialihkan ke halaman konfirmasi pesanan lunas.
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
                <button type="button" @click="poll(true)" :disabled="isChecking"
                   class="w-full sm:w-auto px-6 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-semibold text-sm text-center transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50">
                    <svg x-show="isChecking" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-text="isChecking ? 'Memeriksa ke Gateway...' : 'Cek Status Pembayaran Sekarang'"></span>
                </button>
                @if(!empty($snapToken))
                    <button type="button" @click="reopenPayment()"
                       class="w-full sm:w-auto px-5 py-2.5 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 font-semibold text-sm text-center transition-colors">
                        Buka Ulang Jendela Pembayaran
                    </button>
                @endif
                <a href="{{ route('dashboard.index') }}"
                   class="w-full sm:w-auto px-5 py-2.5 rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-sm text-center transition-colors">
                    Lihat Dashboard
                </a>
            </div>

            <p class="text-center text-xs text-slate-400 mt-6">
                Terakhir diperiksa: <span x-text="lastChecked">--</span>
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
        statusClass: 'bg-amber-100 text-amber-800',
        statusDot: 'bg-amber-500',
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
                'pending': { l: 'Menunggu Pembayaran', c: 'bg-amber-100 text-amber-800', d: 'bg-amber-500' },
                'paid': { l: 'Lunas — Menyiapkan Server', c: 'bg-emerald-100 text-emerald-800', d: 'bg-emerald-500' },
                'provisioning': { l: 'Sedang Provisioning', c: 'bg-cyan-100 text-cyan-800', d: 'bg-cyan-500' },
                'active': { l: 'Aktif', c: 'bg-emerald-100 text-emerald-800', d: 'bg-emerald-500' },
                'cancelled': { l: 'Dibatalkan', c: 'bg-rose-100 text-rose-800', d: 'bg-rose-500' },
                'expired': { l: 'Kadaluarsa', c: 'bg-slate-200 text-slate-700', d: 'bg-slate-500' },
                'failed': { l: 'Gagal', c: 'bg-rose-100 text-rose-800', d: 'bg-rose-500' },
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
