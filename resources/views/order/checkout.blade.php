@extends('layouts.app', ['title' => 'CravaCloud Checkout', 'robots' => 'noindex, follow'])

@section('content')
<div class="pt-6 sm:pt-10 pb-32 sm:pb-20 bg-cream min-h-screen" id="checkout-wizard-top" x-data="checkoutState()" x-init="init()">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Top Editorial Breadcrumb & Progress Header --}}
        <div class="border-b border-hairline pb-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
                <div>
                    <a href="{{ route('home') }}" class="font-mono text-[11px] uppercase tracking-widest text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5 mb-2">
                        <span>←</span>
                        <span>Kembali ke Katalog CravaCloud</span>
                    </a>
                    <h1 class="font-display text-2xl sm:text-4xl font-bold tracking-tight text-ink uppercase">
                        Checkout Layanan Cloud
                    </h1>
                </div>
                <div class="font-mono text-xs text-muted uppercase tracking-wider">
                    Langkah <span class="text-ink font-bold" x-text="'0' + currentStepNumber"></span> dari <span class="text-ink font-bold" x-text="'0' + totalStepCount"></span> —
                    <span class="text-electric font-bold" x-text="currentStepTitle"></span>
                </div>
            </div>

            {{-- Stepper Bar --}}
            <div class="hidden sm:block">
                <div class="grid grid-cols-3 gap-px bg-hairline border border-hairline">
                    @foreach(['Konfigurasi VPS', 'Informasi Akun', 'Metode Pembayaran'] as $index => $title)
                        <div class="p-4 flex items-center gap-3 transition-colors"
                             :class="currentSlide === {{ $index }} ? 'bg-white border-b-2 border-b-electric' : (currentSlide > {{ $index }} ? 'bg-creamDark/60' : 'bg-white')">
                            <span class="w-7 h-7 font-mono text-xs font-bold flex items-center justify-center shrink-0"
                                  :class="currentSlide === {{ $index }} ? 'bg-electric text-white' : (currentSlide > {{ $index }} ? 'bg-ink text-cream' : 'bg-creamDark text-muted border border-hairline')">
                                <template x-if="currentSlide > {{ $index }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                </template>
                                <template x-if="currentSlide <= {{ $index }}">
                                    <span>0{{ $index + 1 }}</span>
                                </template>
                            </span>
                            <div>
                                <span class="font-mono text-[10px] uppercase tracking-wider text-muted block">Step 0{{ $index + 1 }}</span>
                                <span class="font-display text-sm font-bold" :class="currentSlide >= {{ $index }} ? 'text-ink' : 'text-muted'">{{ $title }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Mobile Compact Progress Bar --}}
            <div class="sm:hidden bg-white border border-hairline p-3 flex items-center justify-between gap-3">
                <span class="font-mono text-xs font-bold text-ink uppercase" x-text="currentStepTitle"></span>
                <div class="flex items-center gap-1.5">
                    <template x-for="i in totalStepCount" :key="i">
                        <span class="h-1.5 w-6 transition-colors"
                              :class="i < currentStepNumber ? 'bg-ink' : (i === currentStepNumber ? 'bg-electric' : 'bg-hairline')"></span>
                    </template>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left Column: Active Checkout Slide --}}
            <div class="lg:col-span-2">

                {{-- Maintenance Alert Banner if Package Is Inactive --}}
                <div x-show="isPackageInactive" x-cloak class="mb-6 bg-amber-50 border-2 border-amber-600 p-5 flex items-start gap-4">
                    <div class="w-9 h-9 bg-amber-600 text-white flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="square" stroke-linejoin="miter" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-display text-sm font-bold text-amber-950 uppercase">Paket Sementara Dinonaktifkan</h3>
                            <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 bg-amber-200 text-amber-950 border border-amber-400">
                                Pemeliharaan Sistem
                            </span>
                        </div>
                        <p class="text-xs text-amber-900 leading-relaxed">
                            Pemesanan untuk paket ini ditangguhkan sementara oleh tim CravaCloud karena pemeliharaan infrastruktur. Anda masih dapat meninjau rincian paket atau memilih tier aktif lainnya.
                        </p>
                    </div>
                </div>

                {{-- SLIDE 0: Configuration (Cloud VPS) --}}
                <div x-show="currentSlide === 0"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    @include('order.partials.slide-configuration')
                </div>

                {{-- SLIDE 1: Account Information --}}
                <div x-show="currentSlide === 1"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="space-y-6">
                    @include('order.partials.slide-account')
                </div>

                {{-- SLIDE 2: Payment Method & Confirmation --}}
                <div x-show="currentSlide === 2"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    @include('order.partials.slide-payment')
                </div>

                {{-- Navigation Footer Controls --}}
                <div class="mt-6 pt-6 border-t border-hairline flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3">
                    {{-- Back Buttons --}}
                    <div>
                        <button type="button" @click="previousSlide()" x-show="currentSlide > 0"
                                class="w-full sm:w-auto rounded-none px-5 py-3 bg-white border border-ink hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                            ← <span x-text="currentSlide === 2 ? 'Kembali ke Informasi Akun' : 'Kembali ke Konfigurasi VPS'"></span>
                        </button>
                    </div>

                    {{-- Forward / Submit Controls --}}
                    <div x-show="currentSlide === 0" class="w-full sm:w-auto sm:ml-auto">
                        <button type="button"
                                @click="nextSlide()"
                                :disabled="isPackageInactive"
                                :class="isPackageInactive ? 'bg-amber-700 cursor-not-allowed opacity-75' : 'bg-electric hover:bg-electricHover cursor-pointer'"
                                class="w-full sm:w-auto rounded-none px-7 py-3.5 text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors flex items-center justify-center gap-2">
                            <span x-show="!isPackageInactive">Lanjut ke Informasi Akun →</span>
                            <span x-show="isPackageInactive" class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Pemesanan Dikunci
                            </span>
                        </button>
                    </div>

                    <div x-show="currentSlide === 1" class="w-full sm:w-auto sm:ml-auto">
                        <button type="button"
                                @click="nextSlide()"
                                :disabled="isPackageInactive"
                                :class="isPackageInactive ? 'bg-amber-700 cursor-not-allowed opacity-75' : 'bg-electric hover:bg-electricHover cursor-pointer'"
                                class="w-full sm:w-auto rounded-none px-7 py-3.5 text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors flex items-center justify-center gap-2">
                            <span x-show="!isPackageInactive">Lanjut ke Konfirmasi Pembayaran →</span>
                            <span x-show="isPackageInactive" class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Pemesanan Dikunci
                            </span>
                        </button>
                    </div>

                    <div x-show="currentSlide === 2" class="w-full sm:w-auto sm:ml-auto flex flex-col items-stretch sm:items-end gap-2">
                        <p x-show="!termsAccepted && !isPackageInactive" x-cloak class="font-mono text-[11px] font-bold text-muted text-center sm:text-right">
                            Centang persetujuan ketentuan untuk melanjutkan pembayaran.
                        </p>
                        <p x-show="isPackageInactive" x-cloak class="font-mono text-[11px] font-bold text-amber-700 text-center sm:text-right">
                            Paket dinonaktifkan sementara untuk pemeliharaan sistem.
                        </p>
                        <button type="button"
                                @click="isPackageInactive ? null : submitCheckout()"
                                :disabled="isSubmitting || !termsAccepted || isPackageInactive"
                                class="w-full sm:w-auto rounded-none px-8 py-4 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors disabled:opacity-45 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer">
                            <svg x-show="!isSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <svg x-show="isSubmitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="isSubmitting ? 'Memproses Pesanan...' : (isPackageInactive ? 'Paket Dinonaktifkan' : 'Bayar Sekarang')"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right Column: Sticky Order Summary (Desktop) --}}
            <div class="hidden lg:block lg:col-span-1">
                <div class="sticky top-24 border border-ink bg-white p-6 space-y-5">
                    <div class="flex items-center justify-between pb-4 border-b border-hairline">
                        <div>
                            <span class="font-mono text-[10px] uppercase tracking-widest text-muted block">ORDER SPEC</span>
                            <h3 class="font-display font-bold text-ink text-lg">Ringkasan Pesanan</h3>
                        </div>
                        <span class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase tracking-wider bg-ink text-cream">
                            Cloud VPS
                        </span>
                    </div>

                    {{-- Standard Cloud VPS Spec Summary --}}
                    <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-muted font-mono uppercase text-[11px]">Hostname</span>
                                <span class="font-mono font-bold text-ink text-right break-all" x-text="vpsName || '-'"></span>
                            </div>
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-muted font-mono uppercase text-[11px]">Paket</span>
                                <div class="text-right">
                                    <span class="font-display font-bold text-ink block" x-text="currentSpec ? currentSpec.name : 'Pilih Paket'"></span>
                                    <span x-show="currentSpec" class="font-mono text-[10px] text-muted block"
                                          x-text="currentSpec ? (currentSpec.cpu + ' vCPU · ' + currentSpec.ram + ' GB · ' + currentSpec.disk + ' GB NVMe') : ''"></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-muted font-mono uppercase text-[11px]">Datacenter</span>
                                <span class="font-mono uppercase font-bold text-ink text-right" x-text="datacenter"></span>
                            </div>
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-muted font-mono uppercase text-[11px]">OS</span>
                                <span class="font-semibold text-ink text-right"
                                      x-text="operatingSystems[os] || (os === 'ubuntu2404' ? 'Ubuntu Server 24.04 LTS' : 'Ubuntu Server 22.04 LTS')"></span>
                            </div>
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-muted font-mono uppercase text-[11px]">Stack</span>
                                <span class="font-semibold text-ink text-right" x-text="controlPanelLabel"></span>
                            </div>
                        </div>

                    {{-- Promo & Price Breakdown --}}
                    <div class="border-t border-ink pt-4 space-y-3">
                        <div>
                            <label class="font-mono text-[10px] uppercase text-muted block mb-1">Kode Promo / Kupon</label>
                            <div class="flex gap-1.5">
                                <input type="text" x-model="couponCode" placeholder="KODEPROMO"
                                       class="flex-1 px-2.5 py-1.5 border border-hairline bg-white font-mono text-xs uppercase focus:outline-none focus:border-electric">
                                <button type="button" @click="refreshPromoPreview()" :disabled="applyingPromo"
                                        class="px-3 py-1.5 bg-ink hover:bg-electric text-white font-mono text-[10px] font-bold uppercase cursor-pointer">
                                    Pakai
                                </button>
                            </div>
                            <p x-show="couponError" x-text="couponError" class="font-mono text-[10px] text-red-700 mt-1"></p>
                            <p x-show="appliedCoupon && !couponError" class="font-mono text-[10px] text-emerald-700 mt-1">
                                Kupon <strong x-text="appliedCoupon"></strong> aktif.
                            </p>
                        </div>

                        @if(auth()->check())
                            @php $userCredit = \App\Models\CreditTransaction::balanceFor(auth()->id()); @endphp
                            @if($userCredit > 0)
                                <label class="flex items-center justify-between gap-2 p-2.5 border border-hairline bg-creamDark/30 text-xs cursor-pointer">
                                    <span class="flex items-center gap-2">
                                        <input type="checkbox" x-model="applyCredit" @change="refreshPromoPreview()" class="w-4 h-4 border-ink accent-electric">
                                        <span class="font-medium text-ink">Gunakan Saldo Kredit</span>
                                    </span>
                                    <span class="font-mono text-[11px] font-bold text-electric">Rp {{ number_format($userCredit, 0, ',', '.') }}</span>
                                </label>
                            @endif
                        @endif

                        <template x-if="priceBreakdown && (priceBreakdown.coupon_discount > 0 || priceBreakdown.tax_amount > 0 || priceBreakdown.credit_applied > 0)">
                            <div class="space-y-1 text-[11px] font-mono border-t border-hairline pt-2 text-muted">
                                <div class="flex justify-between"><span>Subtotal</span><span x-text="formatRupiah(priceBreakdown.gross)"></span></div>
                                <div class="flex justify-between text-emerald-700" x-show="priceBreakdown.coupon_discount > 0"><span>Diskon Kupon</span><span x-text="'-' + formatRupiah(priceBreakdown.coupon_discount)"></span></div>
                                <div class="flex justify-between" x-show="priceBreakdown.tax_amount > 0"><span>Pajak</span><span x-text="formatRupiah(priceBreakdown.tax_amount)"></span></div>
                                <div class="flex justify-between text-electric" x-show="priceBreakdown.credit_applied > 0"><span>Kredit Akun</span><span x-text="'-' + formatRupiah(priceBreakdown.credit_applied)"></span></div>
                            </div>
                        </template>

                        <div class="flex justify-between items-baseline">
                            <span class="font-mono text-xs uppercase font-bold text-ink">Total Tagihan</span>
                            <span class="font-mono text-2xl font-bold text-electric" x-text="formatRupiah(totalPrice)"></span>
                        </div>
                        <p class="font-mono text-[10px] text-muted">Siklus penagihan bulanan • Tanpa biaya setup tersembunyi</p>
                    </div>

                    {{-- Available Payment Logos --}}
                    <div class="border-t border-hairline pt-4">
                        <p class="font-mono text-[10px] uppercase tracking-wider text-muted mb-3 text-center">Metode Pembayaran Otomatis</p>
                        <div class="flex flex-wrap justify-center gap-1.5">
                            @foreach([
                                'qris' => 'QRIS',
                                'va_bca' => 'BCA',
                                'va_mandiri' => 'Mandiri',
                                'va_bri' => 'BRI',
                                'va_bni' => 'BNI',
                                'va_permata' => 'Permata',
                                'va_cimb' => 'CIMB Niaga',
                                'gopay' => 'GoPay',
                                'ewallet_shopeepay' => 'ShopeePay',
                                'indomaret' => 'Indomaret'
                            ] as $file => $name)
                                <div class="w-10 h-6 flex items-center justify-center bg-white border border-hairline px-1" title="{{ $name }}">
                                    <img src="{{ asset('images/payments/' . $file . '.svg') }}" alt="{{ $name }}" class="max-h-3.5 object-contain">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile Floating Bottom Bar & Expandable Order Summary Drawer (< lg) --}}
    <div x-show="mobileSummaryOpen" x-cloak
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileSummaryOpen = false"
         class="lg:hidden fixed inset-0 bg-ink/50 z-40"></div>

    <div class="lg:hidden fixed bottom-3 inset-x-3 z-50 max-w-lg mx-auto pointer-events-none">
        <div class="pointer-events-auto bg-white border-2 border-ink overflow-hidden">

            <div x-show="mobileSummaryOpen" x-cloak
                 x-transition:enter="transition-all ease-out duration-200"
                 x-transition:enter-start="opacity-0 max-h-0"
                 x-transition:enter-end="opacity-100 max-h-[75vh]"
                 x-transition:leave="transition-all ease-in duration-150"
                 x-transition:leave-start="opacity-100 max-h-[75vh]"
                 x-transition:leave-end="opacity-0 max-h-0"
                 class="border-b border-hairline overflow-hidden flex flex-col">

                <div class="px-4 py-3 bg-creamDark border-b border-hairline flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-electric"></span>
                        <h3 class="font-mono text-xs font-bold text-ink uppercase tracking-wider">Ringkasan Pesanan</h3>
                        <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">Cloud VPS</span>
                    </div>
                    <button type="button" @click="mobileSummaryOpen = false"
                            class="w-7 h-7 border border-hairline bg-white text-ink flex items-center justify-center cursor-pointer"
                            aria-label="Tutup Rincian">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-4 space-y-3 max-h-[55vh] overflow-y-auto text-xs bg-white">
                    <div class="bg-creamDark/40 p-3 border border-hairline space-y-2">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span class="font-mono text-[10px] text-muted uppercase block">Paket Layanan</span>
                                <span class="font-display font-bold text-ink text-sm block" x-text="currentSpec ? currentSpec.name : 'Pilih Paket'"></span>
                            </div>
                            <span class="font-mono font-bold text-electric text-xs shrink-0" x-text="formatRupiah(totalPrice)"></span>
                        </div>
                        <div class="border-t border-hairline pt-2 grid grid-cols-2 gap-2 font-mono text-[11px]">
                            <div>
                                <span class="text-muted block">Resource:</span>
                                <span class="font-bold text-ink" x-text="currentSpec ? (currentSpec.cpu + ' vCPU · ' + currentSpec.ram + ' GB') : '-'"></span>
                            </div>
                            <div>
                                <span class="text-muted block">Storage:</span>
                                <span class="font-bold text-ink" x-text="currentSpec ? (currentSpec.disk + ' GB NVMe') : '-'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-creamDark/40 p-3 border border-hairline space-y-1.5 font-mono text-[11px]">
                        <div class="flex justify-between gap-2">
                            <span class="text-muted">Hostname:</span>
                            <span class="text-ink font-bold" x-text="vpsName || '-'"></span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-muted">Datacenter:</span>
                            <span class="uppercase text-ink font-semibold" x-text="datacenter"></span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-muted">Stack:</span>
                            <span class="text-ink font-semibold" x-text="controlPanelLabel"></span>
                        </div>
                    </div>

                    <div class="border-t border-hairline pt-3 flex justify-between items-center font-mono">
                        <span class="text-xs uppercase font-bold text-ink">Total Tagihan</span>
                        <span class="text-base font-bold text-electric" x-text="formatRupiah(totalPrice)"></span>
                    </div>

                    <button type="button" @click="mobileSummaryOpen = false"
                            class="w-full py-2.5 bg-creamDark border border-hairline text-ink font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">
                        Tutup Rincian
                    </button>
                </div>
            </div>

            <button type="button"
                    @click="mobileSummaryOpen = !mobileSummaryOpen"
                    class="w-full px-4 py-3 flex items-center justify-between bg-white hover:bg-creamDark/40 transition-colors text-left select-none cursor-pointer">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 bg-electric text-white flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-[10px] font-bold uppercase text-ink">Ringkasan</span>
                            <span class="font-mono text-[9px] font-bold px-1.5 py-0.5 bg-creamDark border border-hairline text-ink shrink-0">VPS</span>
                        </div>
                        <span class="text-[11px] text-muted block truncate" x-text="currentSpec ? currentSpec.name : 'Pilih Paket'"></span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0 pl-2">
                    <div class="text-right">
                        <span class="font-mono text-[10px] text-muted uppercase block leading-tight">Total</span>
                        <span class="font-mono font-bold text-electric text-sm" x-text="formatRupiah(totalPrice)"></span>
                    </div>
                    <div class="w-7 h-7 border border-hairline bg-creamDark flex items-center justify-center text-ink transition-transform duration-200"
                         :class="mobileSummaryOpen ? 'rotate-180' : ''">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 15l7-7 7 7"/>
                        </svg>
                    </div>
                </div>
            </button>
        </div>
    </div>

    @include('components.stack-warning-modal', ['context' => 'checkout'])
</div>

<script>
function checkoutState() {
    return {
        currentSlide: 0,
        mobileSummaryOpen: false,
        isSubmitting: false,
        termsAccepted: false,
        hasExplicitSpec: {{ (!empty($hasExplicitSpec)) ? 'true' : 'false' }},
        selectedSpec: {{ $selectedSpec ? $selectedSpec->id : 'null' }},
        specs: {{ Js::from($specs->keyBy('id')) }},
        billingCycle: 'monthly',
        provider: 'cravacloud',
        vpsName: '',
        rootPassword: '',
        showRootPassword: false,
        stackType: 'none',
        controlPanel: 'none',
        stackSearch: '',
        stackCategory: 'all',
        acknowledgedWarnings: {},
        warningModalOpen: false,
        warningModalStack: null,
        warningModalMode: 'license',
        datacenter: 'indonesia',
        os: 'ubuntu2404',
        operatingSystems: {{ Js::from(\App\Models\Order::checkoutOperatingSystems()) }},
        stackSupportedOs: {{ Js::from($stackSupportedOs ?? app(\App\Services\Provisioning\Stacks\StackRegistry::class)->supportedOsMap()) }},
        stackCatalog: {{ Js::from($stackCatalog ?? app(\App\Services\Provisioning\Stacks\StackRegistry::class)->uiCatalog(checkoutOnly: true)) }},
        stackLabels: {{ Js::from($stackLabels ?? \App\Models\Order::stackLabels()) }},
        paymentMethod: 'online_payment',
        selectedPaymentImage: 'qris.svg',
        paymentMethods: {
            online_payment: { name: 'Pembayaran Otomatis Online (Midtrans)', image: 'midtrans.svg', description: 'QRIS, Virtual Account Bank (BCA, Mandiri, BNI, BRI, Permata, CIMB), GoPay, ShopeePay, Kartu Kredit' },
            midtrans_snap: { name: 'Pembayaran Otomatis Online (Midtrans)', image: 'midtrans.svg', description: 'QRIS, Virtual Account Bank, E-Wallet, Kartu Kredit' },
            qris: { name: 'QRIS (Midtrans)', image: 'qris.svg', description: 'Semua e-wallet & mobile banking instan via Midtrans' },
            mandiri_va: { name: 'Mandiri Virtual Account', image: 'va_mandiri.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            bni_va: { name: 'BNI Virtual Account', image: 'va_bni.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            bri_va: { name: 'BRI Virtual Account', image: 'va_bri.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            cimb_va: { name: 'CIMB Niaga VA', image: 'va_cimb.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            permata_va: { name: 'Permata Virtual Account', image: 'va_permata.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            bca_va: { name: 'BCA Virtual Account', image: 'va_bca.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            bsi_va: { name: 'BSI Virtual Account', image: 'va_bsi.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            danamon_va: { name: 'Danamon Virtual Account', image: 'va_danamon.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            seabank_va: { name: 'SeaBank Virtual Account', image: 'va_seabank.svg', description: 'Virtual Account otomatis 24 jam via Midtrans' },
            credit_card: { name: 'Kartu Kredit / Debit', image: 'credit_card.svg', description: 'Visa / Mastercard via Midtrans' },
            gopay: { name: 'GoPay', image: 'gopay.svg', description: 'Pembayaran instan GoPay via Midtrans' },
            ovo: { name: 'OVO', image: 'ewallet_ovo.svg', description: 'Dompet digital via Midtrans' },
            dana: { name: 'DANA', image: 'dana.svg', description: 'Dompet digital via Midtrans' },
            shopeepay: { name: 'ShopeePay', image: 'ewallet_shopeepay.svg', description: 'Pembayaran instan ShopeePay via Midtrans' },
            indomaret: { name: 'Indomaret', image: 'indomaret.svg', description: 'Bayar tunai di gerai Indomaret via Midtrans' },
            akulaku: { name: 'Akulaku PayLater', image: 'akulaku.svg', description: 'Cicilan & PayLater via Midtrans' }
        },

        // Auth & User State
        isLoggedIn: {{ auth()->check() ? 'true' : 'false' }},
        currentUser: {
            id: {{ auth()->id() ?? 'null' }},
            full_name: '{{ addslashes(auth()->user()?->full_name ?? '') }}',
            email: '{{ addslashes(auth()->user()?->email ?? '') }}',
            phone: '{{ addslashes(auth()->user()?->phone ?? '') }}'
        },
        authTab: 'register',
        registerFullName: '',
        registerUsername: '',
        registerEmail: '',
        registerPhone: '',
        registerPassword: '',
        registerPasswordConfirmation: '',
        couponCode: '',
        appliedCoupon: '',
        couponError: '',
        applyCredit: false,
        priceBreakdown: null,
        applyingPromo: false,
        showRegisterPassword: false,
        showRegisterConfirmPassword: false,
        loginIdentifier: '',
        loginPassword: '',
        showLoginPassword: false,
        isLoggingIn: false,
        loginError: '',
        csrfToken: '{{ csrf_token() }}',

        // Computed properties
        get selectedPaymentMethod() {
            return this.paymentMethods[this.paymentMethod] || null;
        },
        get currentSpec() {
            return this.specs[this.selectedSpec] || null;
        },
        get isPackageInactive() {
            return Boolean(this.currentSpec && !this.currentSpec.is_active);
        },
        get totalStepCount() {
            return 3;
        },
        get currentStepNumber() {
            return this.currentSlide + 1;
        },
        get currentStepTitle() {
            const titles = ['Konfigurasi VPS', 'Informasi Akun', 'Metode Pembayaran'];
            return titles[this.currentSlide] || 'Konfigurasi VPS';
        },
        get parsedFeatures() {
            const s = this.currentSpec;
            if (!s || !s.features) return [];
            if (Array.isArray(s.features)) return s.features;
            try {
                return JSON.parse(s.features);
            } catch (e) {
                return [];
            }
        },
        get currentBaseMonthlyPrice() {
            return this.currentSpec ? parseFloat(this.currentSpec.sell_price) : 115000;
        },
        get totalPrice() {
            if (this.priceBreakdown && typeof this.priceBreakdown.total === 'number') {
                return this.priceBreakdown.total;
            }
            return this.currentBaseMonthlyPrice;
        },
        async refreshPromoPreview() {
            this.applyingPromo = true;
            this.couponError = '';
            try {
                const res = await fetch('/checkout/apply-coupon', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        spec_id: this.selectedSpec,
                        coupon_code: (this.couponCode || '').trim() || null,
                        apply_credit: this.applyCredit ? 1 : 0,
                    }),
                });
                const d = await res.json().catch(() => null);
                if (d && d.breakdown) {
                    this.priceBreakdown = d.breakdown;
                }
                if (!res.ok || (d && d.ok === false)) {
                    this.couponError = (d && d.error) || 'Kode promo tidak valid.';
                    this.appliedCoupon = '';
                } else {
                    this.appliedCoupon = (this.couponCode || '').trim();
                }
            } catch (e) {
                this.couponError = 'Gagal mengecek promo.';
            } finally {
                this.applyingPromo = false;
            }
        },
        get planRamGb() {
            return this.currentSpec ? parseInt(this.currentSpec.ram || 0, 10) : 0;
        },
        get planName() {
            return this.currentSpec ? (this.currentSpec.name || 'Paket VPS') : 'Paket VPS';
        },
        get selectedStackDef() {
            return this.stackCatalog.find((s) => s.key === this.controlPanel) || null;
        },
        get isWarningAcknowledged() {
            const def = this.selectedStackDef;
            if (!def || !def.needsWarningConfirmation) return true;
            return Boolean(this.acknowledgedWarnings[def.key]);
        },
        get isLicenseAcknowledged() {
            const def = this.selectedStackDef;
            if (!def || !def.requiresLicense) return false;
            return Boolean(this.acknowledgedWarnings[def.key]);
        },
        get activeUiGroup() {
            if (this.stackType === 'control_panel') return 'panel';
            if (this.stackType === 'app') return 'app';
            return 'none';
        },
        get groupStacks() {
            return this.stackCatalog.filter((s) => s.uiGroup === this.activeUiGroup);
        },
        get hasPaidPanelsInGroup() {
            return this.groupStacks.some((s) => Boolean(s.requiresLicense));
        },
        get availableStackCategories() {
            const cats = [];
            for (const item of this.groupStacks) {
                if (item.category && !cats.includes(item.category)) {
                    cats.push(item.category);
                }
            }
            return cats;
        },
        get filteredStacks() {
            const q = (this.stackSearch || '').trim().toLowerCase();
            const cat = this.stackCategory || 'all';
            return this.groupStacks.filter((s) => {
                if (cat === '__free' && s.requiresLicense) return false;
                if (cat === '__paid' && !s.requiresLicense) return false;
                if (cat !== 'all' && cat !== '__free' && cat !== '__paid' && s.category !== cat) return false;
                if (q === '') return true;
                return (s.label || '').toLowerCase().includes(q)
                    || (s.key || '').toLowerCase().includes(q)
                    || (s.description || '').toLowerCase().includes(q)
                    || (s.category || '').toLowerCase().includes(q);
            });
        },
        get controlPanelLabel() {
            return this.stackLabels[this.controlPanel]
                || this.stackCatalog.find((s) => s.key === this.controlPanel)?.label
                || 'Tanpa Control Panel';
        },
        get compatibleOperatingSystems() {
            const all = this.operatingSystems || {};
            const allowed = this.stackSupportedOs[this.controlPanel];
            if (!Array.isArray(allowed) || allowed.length === 0) return all;
            const filtered = {};
            for (const key of Object.keys(all)) {
                if (allowed.includes(key)) filtered[key] = all[key];
            }
            return Object.keys(filtered).length > 0 ? filtered : all;
        },
        ensureCompatibleOs() {
            const compat = this.compatibleOperatingSystems;
            if (!compat[this.os]) {
                this.os = compat.ubuntu2404 ? 'ubuntu2404' : Object.keys(compat)[0];
            }
        },

        applyQueryPrefills() {
            try {
                const params = new URLSearchParams(window.location.search);
                const host = params.get('hostname');
                if (host && /^[A-Za-z0-9][A-Za-z0-9-]*$/.test(host)) {
                    this.vpsName = host;
                }
                const stack = params.get('stack');
                if (stack) {
                    const found = this.stackCatalog.find((s) => s.key === stack);
                    if (found) {
                        this.stackType = found.uiGroup === 'panel' ? 'control_panel' : (found.uiGroup === 'app' ? 'app' : 'none');
                        if (found.uiGroup === 'none' || (found.hardBlockOnRam && this.planRamGb > 0 && this.planRamGb < (found.minRamGb || 2))) {
                            this.controlPanel = 'none';
                            if (found.uiGroup !== 'none') {
                                this.handleStackClick(found);
                            }
                        } else if (found.needsWarningConfirmation && !this.acknowledgedWarnings[found.key]) {
                            this.controlPanel = 'none';
                            this.handleStackClick(found);
                        } else {
                            this.controlPanel = found.key;
                        }
                    } else if (stack === 'debian12') {
                        this.stackType = 'none';
                        this.controlPanel = 'none';
                        if (this.operatingSystems && this.operatingSystems.debian12) {
                            this.os = 'debian12';
                        }
                    } else if (stack === 'none' || stack === 'ubuntu2404') {
                        this.stackType = 'none';
                        this.controlPanel = 'none';
                    }
                }
            } catch (e) {}
        },

        init() {
            this.restoreDraft();
            this.applyQueryPrefills();

            if (!this.selectedSpec && Object.keys(this.specs).length > 0) {
                this.selectedSpec = parseInt(Object.keys(this.specs)[0]);
            }
            this.ensureCompatibleOs();
            this.saveDraft();

            this.$watch('selectedSpec', () => {
                this.ensureCompatibleOs();
                this.saveDraft();
            });

            this.$watch('vpsName', () => this.saveDraft());
            this.$watch('provider', () => this.saveDraft());
            this.$watch('datacenter', () => this.saveDraft());
            this.$watch('os', () => this.saveDraft());
            this.$watch('controlPanel', () => { this.ensureCompatibleOs(); this.saveDraft(); });
            this.$watch('billingCycle', () => this.saveDraft());
            this.$watch('paymentMethod', () => this.saveDraft());
            this.$watch('registerFullName', () => this.saveDraft());
            this.$watch('registerUsername', () => this.saveDraft());
            this.$watch('registerEmail', () => this.saveDraft());
            this.$watch('registerPhone', () => this.saveDraft());
        },

        saveDraft() {
            try {
                const draft = {
                    selectedSpec: this.selectedSpec,
                    billingCycle: this.billingCycle,
                    provider: this.provider,
                    vpsName: this.vpsName,
                    controlPanel: this.controlPanel,
                    acknowledgedWarnings: this.acknowledgedWarnings,
                    datacenter: this.datacenter,
                    os: this.os,
                    paymentMethod: this.paymentMethod,
                    registerFullName: this.registerFullName,
                    registerUsername: this.registerUsername,
                    registerEmail: this.registerEmail,
                    registerPhone: this.registerPhone,
                };
                localStorage.setItem('cv_checkout_draft', JSON.stringify(draft));
            } catch (e) {}
        },

        restoreDraft() {
            try {
                const raw = localStorage.getItem('cv_checkout_draft');
                if (!raw) return;
                const draft = JSON.parse(raw);

                if (!this.hasExplicitSpec && draft.selectedSpec && this.specs[draft.selectedSpec]) {
                    this.selectedSpec = draft.selectedSpec;
                }

                if (draft.billingCycle) this.billingCycle = draft.billingCycle;
                this.provider = 'cravacloud';

                if (draft.vpsName) {
                    this.vpsName = draft.vpsName;
                }
                if (draft.acknowledgedWarnings && typeof draft.acknowledgedWarnings === 'object') {
                    this.acknowledgedWarnings = draft.acknowledgedWarnings;
                }
                if (draft.controlPanel) {
                    const found = this.stackCatalog.find((s) => s.key === draft.controlPanel);
                    if (!found || found.uiGroup === 'none') {
                        this.controlPanel = 'none';
                        this.stackType = 'none';
                    } else if (found.hardBlockOnRam && this.planRamGb > 0 && this.planRamGb < (found.minRamGb || 2)) {
                        this.controlPanel = 'none';
                        this.stackType = 'none';
                    } else {
                        this.controlPanel = found.key;
                        this.stackType = found.uiGroup === 'panel' ? 'control_panel' : 'app';
                    }
                }
                if (draft.datacenter) this.datacenter = draft.datacenter;
                if (draft.os) this.os = draft.os;
                if (draft.paymentMethod && this.paymentMethods[draft.paymentMethod]) {
                    this.paymentMethod = draft.paymentMethod;
                } else {
                    this.paymentMethod = 'online_payment';
                }
                if (!this.isLoggedIn) {
                    if (draft.registerFullName) this.registerFullName = draft.registerFullName;
                    if (draft.registerUsername) this.registerUsername = draft.registerUsername;
                    if (draft.registerEmail) this.registerEmail = draft.registerEmail;
                    if (draft.registerPhone) this.registerPhone = draft.registerPhone;
                }
            } catch (e) {
                console.warn('Could not restore draft:', e);
            }
        },

        clearDraft() {
            try {
                localStorage.removeItem('cv_checkout_draft');
            } catch (e) {}
        },

        async performQuickLogin() {
            this.loginError = '';
            if (!this.loginIdentifier || !this.loginPassword) {
                this.loginError = 'Harap isi email/username dan password akun Anda.';
                showAlert(this.loginError, { icon: 'warning', title: 'Data Login Belum Lengkap' });
                return false;
            }

            this.isLoggingIn = true;
            try {
                const res = await fetch('/checkout/quick-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        login: this.loginIdentifier,
                        password: this.loginPassword
                    })
                });

                const data = await res.json().catch(() => null);

                if (!res.ok) {
                    this.loginError = data?.message || 'Login gagal. Silakan periksa kembali email/username dan password.';
                    showAlert(this.loginError, { icon: 'error', title: 'Login Gagal' });
                    this.isLoggingIn = false;
                    return false;
                }

                if (data?.requires_2fa) {
                    this.saveDraft();
                    window.location.href = data.redirect || '{{ route('two-factor.challenge') }}';
                    return false;
                }

                if (data?.success && data?.user) {
                    this.currentUser = data.user;
                    this.isLoggedIn = true;
                    if (data.csrf_token) {
                        this.csrfToken = data.csrf_token;
                        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', data.csrf_token);
                    }
                    this.loginPassword = '';
                    this.saveDraft();
                    showAlert('Berhasil masuk sebagai ' + (this.currentUser.full_name || this.currentUser.email), {
                        icon: 'success',
                        title: 'Login Berhasil'
                    });
                    return true;
                }
                return false;
            } catch (err) {
                console.error('Quick login error:', err);
                this.loginError = 'Terjadi kesalahan jaringan saat mencoba masuk. Silakan coba lagi.';
                showAlert(this.loginError, { icon: 'error', title: 'Login Gagal' });
                return false;
            } finally {
                this.isLoggingIn = false;
            }
        },

        handleStackClick(stack) {
            if (!stack) return;
            if (stack.hardBlockOnRam && this.planRamGb > 0 && this.planRamGb < (stack.minRamGb || 2)) {
                this.warningModalStack = stack;
                this.warningModalMode = 'ram';
                this.warningModalOpen = true;
                return;
            }
            if (stack.needsWarningConfirmation && !this.acknowledgedWarnings[stack.key]) {
                this.warningModalStack = stack;
                this.warningModalMode = stack.requiresLicense ? 'license' : (stack.warningType || 'activation');
                this.warningModalOpen = true;
                return;
            }
            this.controlPanel = stack.key;
        },

        confirmStackWarning() {
            if (!this.warningModalStack) return;
            const key = this.warningModalStack.key;
            this.acknowledgedWarnings = { ...this.acknowledgedWarnings, [key]: true };
            this.controlPanel = key;
            this.warningModalOpen = false;
            this.warningModalStack = null;
            this.saveDraft();
        },

        closeStackWarning() {
            this.warningModalOpen = false;
            this.warningModalStack = null;
        },

        selectStack(type) {
            this.stackType = type;
            this.stackSearch = '';
            this.stackCategory = 'all';
            if (type === 'none') {
                this.controlPanel = 'none';
                return;
            }
            const targetGroup = type === 'control_panel' ? 'panel' : 'app';
            const currentDef = this.selectedStackDef;
            if (currentDef && currentDef.uiGroup === targetGroup) {
                return;
            }
            const candidate = this.stackCatalog.find((s) => s.uiGroup === targetGroup && !s.needsWarningConfirmation && (!s.hardBlockOnRam || this.planRamGb === 0 || this.planRamGb >= (s.minRamGb || 2)))
                || this.stackCatalog.find((s) => s.uiGroup === targetGroup);
            if (candidate) {
                if (candidate.hardBlockOnRam && this.planRamGb > 0 && this.planRamGb < (candidate.minRamGb || 2)) {
                    this.handleStackClick(candidate);
                } else if (candidate.needsWarningConfirmation && !this.acknowledgedWarnings[candidate.key]) {
                    this.handleStackClick(candidate);
                } else {
                    this.controlPanel = candidate.key;
                }
            }
        },

        formatRupiah(number) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
        },

        scrollToWizardTop() {
            this.$nextTick(() => {
                const el = document.getElementById('checkout-wizard-top');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        },

        previousSlide() {
            this.mobileSummaryOpen = false;
            if (this.currentSlide > 0) {
                this.currentSlide--;
                this.scrollToWizardTop();
            }
        },

        async nextSlide() {
            this.mobileSummaryOpen = false;
            if (this.isPackageInactive) {
                showAlert('Paket layanan ini sedang dinonaktifkan sementara untuk pemeliharaan sistem.', {
                    icon: 'warning',
                    title: 'Pemesanan Ditangguhkan'
                });
                return;
            }

            if (this.currentSlide === 0) {
                if (!this.validateConfiguration()) {
                    return;
                }
                this.currentSlide = 1;
                this.scrollToWizardTop();
                return;
            }

            if (this.currentSlide === 1) {
                if (!await this.validateAccount()) {
                    return;
                }
                this.currentSlide = 2;
                this.scrollToWizardTop();
                return;
            }
        },

        validateConfiguration() {
            if (!this.vpsName || !/^[A-Za-z0-9][A-Za-z0-9-]*$/.test(this.vpsName)) {
                showAlert('VPS Name wajib diisi dengan huruf, angka, atau tanda hubung.');
                return false;
            }
            if (!this.selectedSpec) {
                showAlert('Silakan pilih paket VPS terlebih dahulu');
                return false;
            }
            const def = this.selectedStackDef;
            if (def) {
                if (def.hardBlockOnRam && this.planRamGb > 0 && this.planRamGb < (def.minRamGb || 2)) {
                    this.handleStackClick(def);
                    return false;
                }
                if (def.needsWarningConfirmation && !this.acknowledgedWarnings[def.key]) {
                    this.handleStackClick(def);
                    return false;
                }
            }
            return true;
        },

        async validateAccount() {
            if (this.isLoggedIn) {
                return true;
            }

            if (this.authTab === 'login') {
                if (!this.loginIdentifier || !this.loginPassword) {
                    showAlert('Silakan lengkapi Email/Username dan Password akun Anda untuk melanjutkan.');
                    return false;
                }
                const loginSuccess = await this.performQuickLogin();
                return Boolean(loginSuccess);
            }

            if (!this.registerFullName || !this.registerEmail || !this.registerPassword) {
                showAlert('Silakan lengkapi Nama Lengkap, Email, dan Password pendaftaran terlebih dahulu.');
                return false;
            }

            if (this.registerPassword.length < 8) {
                showAlert('Password pendaftaran minimal 8 karakter.');
                return false;
            }

            if (this.registerPasswordConfirmation && this.registerPassword !== this.registerPasswordConfirmation) {
                showAlert('Konfirmasi password tidak cocok dengan password yang Anda masukkan.');
                return false;
            }

            return true;
        },

        selectPayment(method, isActive = true) {
            if (!isActive) {
                const methodName = this.paymentMethods[method]?.name || method;
                showAlert('Metode pembayaran ' + methodName + ' sedang tidak aktif. Silakan pilih metode pembayaran lain.', {
                    icon: 'info',
                    title: 'Metode Pembayaran Nonaktif'
                });
                return;
            }
            this.paymentMethod = method;
            this.selectedPaymentImage = this.paymentMethods[method]?.image || 'qris.svg';
        },

        async submitCheckout() {
            if (this.isPackageInactive) {
                showAlert('Paket layanan ini sedang dinonaktifkan sementara untuk pemeliharaan sistem.', {
                    icon: 'warning',
                    title: 'Pemesanan Ditangguhkan'
                });
                return;
            }

            if (!this.paymentMethod) {
                showAlert('Silakan pilih metode pembayaran terlebih dahulu');
                return;
            }

            if (!this.termsAccepted) {
                showAlert('Anda wajib menyetujui Ketentuan Layanan dan Ketentuan Penggunaan sebelum melanjutkan pemesanan');
                return;
            }

            this.isSubmitting = true;

            try {
                const formData = {
                    _token: this.csrfToken,
                    vps_spec_id: this.selectedSpec,
                    billing_cycle: this.billingCycle,
                    provider: this.provider,
                    control_panel: this.controlPanel,
                    datacenter_location: this.datacenter,
                    os: this.os,
                    warning_acknowledged: this.isWarningAcknowledged ? 1 : 0,
                    license_acknowledged: this.isLicenseAcknowledged ? 1 : 0,
                    payment_method: this.paymentMethod,
                    hostname: this.vpsName,
                    coupon_code: this.appliedCoupon || ((this.couponCode || '').trim() || null),
                    apply_credit: this.applyCredit ? 1 : 0,
                    terms_accepted: this.termsAccepted ? 1 : 0,
                    phone: this.isLoggedIn
                        ? (this.currentUser?.phone || '')
                        : (this.registerPhone || ''),
                };

                if (!this.isLoggedIn) {
                    formData.full_name = this.registerFullName;
                    formData.username = this.registerUsername ? this.registerUsername.trim() : null;
                    formData.email = this.registerEmail;
                    formData.password = this.registerPassword;
                }

                const response = await fetch('/checkout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json().catch(() => null);

                if (!response.ok) {
                    let errorMsg = 'Terjadi kesalahan saat memproses pesanan.';
                    if (data) {
                        if (data.message) {
                            errorMsg = data.message;
                        }
                        if (data.errors) {
                            errorMsg = Object.values(data.errors).flat().join('\n');
                        }
                    }
                    showAlert(errorMsg, {
                        icon: 'error',
                        title: 'Checkout Gagal',
                    });
                    this.isSubmitting = false;
                    return;
                }

                this.clearDraft();

                if (data && data.snap_token && window.snap) {
                    this.isSubmitting = false;
                    window.snap.pay(data.snap_token, {
                        onSuccess: () => {
                            window.location.href = '/order/payment/' + data.order_id + '/status';
                        },
                        onPending: () => {
                            window.location.href = '/order/payment/' + data.order_id + '/status';
                        },
                        onError: () => {
                            showAlert('Pembayaran tidak berhasil atau dibatalkan. Anda dapat mengulangi proses pembayaran.', {
                                icon: 'error',
                                title: 'Pembayaran Gagal'
                            });
                            this.isSubmitting = false;
                        },
                        onClose: () => {
                            showAlert('Jendela pembayaran ditutup. Anda dapat melanjutkan pembayaran nanti melalui menu Tagihan di Dashboard.', {
                                icon: 'info',
                                title: 'Pembayaran Ditunda'
                            });
                            this.isSubmitting = false;
                        }
                    });
                    return;
                }

                if (data && data.snap_redirect_url) {
                    window.location.href = data.snap_redirect_url;
                    return;
                }

                if (data && data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else if (data && data.order_id) {
                    window.location.href = '/order/payment/' + data.order_id + '/status';
                } else {
                    window.location.href = '/dashboard';
                }
            } catch (error) {
                console.error('Checkout error:', error);
                showAlert('Terjadi kesalahan jaringan atau koneksi terputus. Silakan coba lagi.', {
                    icon: 'error',
                    title: 'Checkout gagal',
                });
                this.isSubmitting = false;
            }
        }
    };
}
</script>
@endsection

@push('scripts')
    @php
        $midtransClientKey = \App\Services\Payments\MidtransService::getClientKey();
        $midtransSnapJsUrl = \App\Services\Payments\MidtransService::getSnapJsUrl();
    @endphp
    @if(!empty($midtransClientKey))
        <script src="{{ $midtransSnapJsUrl }}" data-client-key="{{ $midtransClientKey }}"></script>
    @endif
@endpush
