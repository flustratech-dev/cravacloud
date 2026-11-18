@extends('layouts.app', ['title' => 'CravaCloud Pembayaran', 'robots' => 'noindex, follow'])

@section('content')
@if(!empty($isMidtrans) && !empty($snapJsUrl) && !empty($snapClientKey))
    <script src="{{ $snapJsUrl }}" data-client-key="{{ $snapClientKey }}"></script>
@endif

<div class="py-10 sm:py-14 bg-cream min-h-screen" x-data="paymentGateway()">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <a href="{{ route('checkout', $order->vps_spec_id) }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
                <span>←</span>
                <span>Kembali ke Checkout</span>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left: Active Payment Gateway Box --}}
            <div class="lg:col-span-2">
                <div class="bg-white border border-ink overflow-hidden">
                    {{-- Header --}}
                    <div class="bg-ink text-cream p-6 border-b border-ink">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-10 h-10 bg-electric text-white flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="square" stroke-linejoin="miter" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h1 class="font-display text-xl sm:text-2xl font-bold text-white tracking-tight">Pembayaran {{ $order->payment_method_name }}</h1>
                                    <p class="text-xs text-cream/70 mt-0.5">Selesaikan pembayaran untuk mengaktifkan VPS Anda</p>
                                </div>
                            </div>
                            <div class="sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-white/15">
                                <div class="font-mono text-[10px] uppercase tracking-widest text-cream/60">Nomor Invoice</div>
                                <div class="font-mono font-bold text-white text-sm mt-0.5">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Content --}}
                    <div class="p-6 sm:p-8">

                        {{-- Lynk.id Payment --}}
                        @if($order->payment_method === 'lynk')
                        <div class="text-center mb-8 flex flex-col items-center justify-center">
                            <div class="w-48 h-20 mx-auto flex items-center justify-center p-3 bg-white border border-hairline mb-5">
                                <img src="{{ asset('images/payments/lynk.svg') }}" alt="Lynk.id Checkout" class="max-h-12 max-w-full object-contain">
                            </div>

                            <h2 class="font-display text-xl font-bold text-ink mb-2">Bayar via Lynk.id Gateway</h2>
                            <p class="text-xs text-muted max-w-md mx-auto mb-5 leading-relaxed">
                                Selesaikan transaksi Anda melalui gateway pembayaran resmi Lynk.id (QRIS, VA Bank, GoPay, OVO, DANA, ShopeePay, dan Kartu Debit/Kredit).
                            </p>

                            <div class="p-4 bg-creamDark/60 border border-hairline max-w-sm w-full mx-auto text-center space-y-1 mb-5">
                                <span class="font-mono text-[10px] uppercase tracking-wider text-muted block">Total Nominal Tagihan</span>
                                <div class="text-2xl font-bold font-mono text-electric">
                                    Rp {{ number_format($order->amount, 0, ',', '.') }}
                                </div>
                            </div>

                            @php
                                $lynkCheckoutUrl = !empty($order->vpsSpec->payment_url) ? $order->vpsSpec->payment_url : null;
                            @endphp

                            @if($lynkCheckoutUrl)
                                <div class="w-full max-w-sm mx-auto mb-5">
                                    <a href="{{ $lynkCheckoutUrl }}" target="_blank"
                                       class="w-full rounded-none inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                                        <span>Buka Halaman Checkout Lynk.id</span>
                                        <span>↗</span>
                                    </a>
                                    <p class="font-mono text-[10px] text-muted mt-2">Tautan checkout aman terbuka di tab baru</p>
                                </div>
                            @endif

                            {{-- Cek Status Block --}}
                            <div class="w-full max-w-sm mx-auto">
                                <div class="p-4 border transition-colors text-center"
                                     :class="{
                                         'bg-creamDark/40 border-hairline': checkStatus === 'idle',
                                         'bg-amber-50 border-amber-500 text-amber-950': checkStatus === 'pending',
                                         'bg-emerald-50 border-emerald-600 text-emerald-950': checkStatus === 'success'
                                     }">
                                    <button type="button"
                                            @click="handleCheckStatus()"
                                            :disabled="isChecking"
                                            class="w-full rounded-none px-5 py-3 font-mono font-bold text-xs uppercase tracking-wider text-white transition-colors flex items-center justify-center gap-2 cursor-pointer"
                                            :class="checkStatus === 'success' ? 'bg-ink hover:bg-electric' : 'bg-electric hover:bg-electricHover disabled:opacity-60'">
                                        <svg x-show="isChecking" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span x-text="buttonText"></span>
                                    </button>

                                    <div class="mt-3 text-xs">
                                        <template x-if="checkStatus === 'idle'">
                                            <p class="text-muted">Setelah menyelesaikan pembayaran di Lynk.id, klik tombol di atas untuk sinkronisasi status server.</p>
                                        </template>
                                        <template x-if="checkStatus === 'pending'">
                                            <div class="space-y-1.5 text-left bg-white p-3 border border-amber-400">
                                                <div class="font-mono font-bold text-amber-900 text-xs">Status: Menunggu Pembayaran (Pending)</div>
                                                <p class="text-[11px] text-amber-800 leading-relaxed">Webhook dari Lynk.id belum diterima. Selesaikan pembayaran di halaman Lynk.id.</p>
                                                <div class="font-mono text-[11px] text-amber-950 pt-1 border-t border-amber-200">
                                                    Verifikasi otomatis: <span x-text="countdownText"></span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="checkStatus === 'success'">
                                            <div class="space-y-1 text-left bg-white p-3 border border-emerald-500">
                                                <div class="font-mono font-bold text-emerald-900 text-xs">Status: Pembayaran Berhasil Diterima</div>
                                                <p class="text-[11px] text-emerald-800">Transaksi Lynk.id terverifikasi. Mengalihkan ke dashboard...</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- QRIS Payment --}}
                        @if(in_array($order->payment_method, ['qris']))
                        <div class="text-center mb-8 flex flex-col items-center justify-center">
                            {{-- Crisp White QR Canvas with Solid Ink Frame for Scanner Clarity --}}
                            <div class="qris-light-box w-72 h-72 sm:w-80 sm:h-80 mx-auto flex items-center justify-center p-4 bg-white border-2 border-ink">
                                <img src="{{ $qrisDataUri }}" alt="QRIS Payment CravaCloud" class="max-w-full max-h-full object-contain">
                            </div>

                            <div class="mt-5 p-4 bg-creamDark/60 border border-hairline max-w-sm w-full mx-auto text-center space-y-1.5">
                                <span class="font-mono text-[10px] uppercase tracking-wider text-muted block">Total Pembayaran Sesuai Tagihan</span>
                                <div class="text-2xl font-bold font-mono text-electric">
                                    Rp {{ number_format($order->amount, 0, ',', '.') }}
                                </div>
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $order->amount }}'); showAlert('Nominal tagihan disalin: Rp {{ number_format($order->amount, 0, ',', '.') }}', { icon: 'success' })"
                                        class="font-mono text-[11px] text-ink hover:text-electric font-bold inline-flex items-center gap-1.5 pt-1 transition-colors cursor-pointer uppercase">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Salin Nominal Tagihan</span>
                                </button>
                            </div>

                            <div class="mt-3 flex items-center justify-center gap-2">
                                <a href="{{ $qrisDataUri }}" target="_blank" download="qris-cravacloud-order-{{ $order->id }}.svg"
                                   class="rounded-none inline-flex items-center gap-1.5 font-mono text-xs font-bold uppercase tracking-wider px-4 py-2.5 bg-white border border-ink hover:bg-creamDark text-ink transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <span>Unduh / Buka QRIS</span>
                                </a>
                            </div>

                            <div class="mt-6 w-full max-w-sm mx-auto">
                                <div class="p-4 border transition-colors text-center"
                                     :class="{
                                         'bg-creamDark/40 border-hairline': checkStatus === 'idle',
                                         'bg-amber-50 border-amber-500 text-amber-950': checkStatus === 'pending',
                                         'bg-emerald-50 border-emerald-600 text-emerald-950': checkStatus === 'success'
                                     }">
                                    <button type="button"
                                            @click="handleCheckStatus()"
                                            :disabled="isChecking"
                                            class="w-full rounded-none px-5 py-3 font-mono font-bold text-xs uppercase tracking-wider text-white transition-colors flex items-center justify-center gap-2 cursor-pointer"
                                            :class="checkStatus === 'success' ? 'bg-ink hover:bg-electric' : 'bg-electric hover:bg-electricHover disabled:opacity-60'">
                                        <svg x-show="isChecking" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span x-text="buttonText"></span>
                                    </button>

                                    <div class="mt-3 text-xs">
                                        <template x-if="checkStatus === 'idle'">
                                            <p class="text-muted">Klik tombol di atas setelah Anda menyelesaikan pembayaran QRIS melalui aplikasi e-wallet atau mobile banking.</p>
                                        </template>
                                        <template x-if="checkStatus === 'pending'">
                                            <div class="space-y-1.5 text-left bg-white p-3 border border-amber-400">
                                                <div class="font-mono font-bold text-amber-900 text-xs">Status: Menunggu Pembayaran (Pending)</div>
                                                <p class="text-[11px] text-amber-800 leading-relaxed">Pembayaran belum terdeteksi pada sistem mutasi. Silakan selesaikan scan QRIS Anda.</p>
                                                <div class="font-mono text-[11px] text-amber-950 pt-1 border-t border-amber-200">
                                                    Verifikasi otomatis: <span x-text="countdownText"></span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="checkStatus === 'success'">
                                            <div class="space-y-1 text-left bg-white p-3 border border-emerald-500">
                                                <div class="font-mono font-bold text-emerald-900 text-xs">Status: Pembayaran Berhasil Diterima</div>
                                                <p class="text-[11px] text-emerald-800">Transaksi QRIS diterima dan masuk antrean aktivasi server. Mengalihkan...</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2 mt-6 text-center">
                                <p class="font-mono text-[10px] uppercase tracking-wider text-muted">Mendukung mobile banking &amp; e-wallet</p>
                                <div class="flex flex-wrap items-center justify-center gap-1.5 max-w-md mx-auto">
                                    @foreach(['va_bca' => 'BCA', 'va_mandiri' => 'Mandiri', 'va_bri' => 'BRI', 'va_bni' => 'BNI', 'gopay' => 'GoPay', 'ewallet_ovo' => 'OVO', 'dana' => 'DANA', 'ewallet_shopeepay' => 'ShopeePay'] as $file => $app)
                                        <div class="w-11 h-7 bg-white border border-hairline flex items-center justify-center p-1" title="{{ $app }}">
                                            <img src="{{ asset('images/payments/' . $file . '.svg') }}" alt="{{ $app }}" class="max-h-4 max-w-full object-contain">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Midtrans Snap Payment --}}
                        @if(!empty($isMidtrans))
                        <div class="text-center mb-8 flex flex-col items-center justify-center">
                            <div class="w-48 h-16 mx-auto flex items-center justify-center p-3 bg-white border border-hairline mb-5">
                                @php
                                    $midtransMethodIcons = [
                                        'mandiri_va' => 'va_mandiri.svg',
                                        'bni_va' => 'va_bni.svg',
                                        'bri_va' => 'va_bri.svg',
                                        'permata_va' => 'va_permata.svg',
                                        'cimb_va' => 'va_cimb.svg',
                                        'gopay' => 'gopay.svg',
                                    ];
                                    $methodIcon = $midtransMethodIcons[$order->payment_method] ?? 'midtrans.svg';
                                @endphp
                                <img src="{{ asset('images/payments/' . $methodIcon) }}" alt="Midtrans Payment" class="max-h-10 max-w-full object-contain">
                            </div>

                            <h2 class="font-display text-xl font-bold text-ink mb-2">
                                Pembayaran {{ $order->payment_method_name }} via Midtrans
                            </h2>
                            <p class="text-xs text-muted max-w-md mx-auto mb-5 leading-relaxed">
                                Transaksi diproses secara instan dan terverifikasi otomatis melalui payment gateway resmi Midtrans (GoPay, QRIS, dan Virtual Account Bank).
                            </p>

                            <div class="p-4 bg-creamDark/60 border border-hairline max-w-sm w-full mx-auto text-center space-y-1 mb-5">
                                <span class="font-mono text-[10px] uppercase tracking-wider text-muted block">Total Nominal Tagihan</span>
                                <div class="text-2xl font-bold font-mono text-electric">
                                    Rp {{ number_format($order->amount, 0, ',', '.') }}
                                </div>
                            </div>

                            @if(!empty($snapToken))
                                <div class="w-full max-w-sm mx-auto space-y-2.5 mb-5">
                                    <button type="button" @click="payWithMidtrans()"
                                            class="w-full rounded-none inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                                        <span>Buka Jendela Pembayaran Midtrans</span>
                                        <span>↗</span>
                                    </button>

                                    @if(!empty($snapRedirectUrl))
                                        <a href="{{ $snapRedirectUrl }}" target="_blank"
                                           class="w-full rounded-none inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-white border border-ink hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                                            <span>Buka di Tab Baru (Halaman Web Midtrans)</span>
                                            <span>↗</span>
                                        </a>
                                    @endif
                                    <p class="font-mono text-[10px] text-muted">Klik tombol di atas jika jendela pembayaran tidak terbuka otomatis.</p>
                                </div>
                            @elseif(!empty($snapError))
                                <div class="w-full max-w-sm mx-auto mb-5 p-4 bg-amber-50 border border-amber-500 text-left">
                                    <div class="font-mono font-bold text-amber-900 text-xs uppercase mb-1">Konfigurasi Gateway Belum Selesai</div>
                                    <p class="text-xs text-amber-900 leading-relaxed">{{ $snapError }}</p>
                                    <p class="font-mono text-[10px] text-amber-800 mt-2">
                                        Admin CravaCloud: Pastikan Server Key dan Client Key terkonfigurasi sesuai mode (Produksi/Sandbox).
                                    </p>
                                </div>
                            @endif

                            <div class="w-full max-w-sm mx-auto">
                                <div class="p-4 border transition-colors text-center"
                                     :class="{
                                         'bg-creamDark/40 border-hairline': checkStatus === 'idle',
                                         'bg-amber-50 border-amber-500 text-amber-950': checkStatus === 'pending',
                                         'bg-emerald-50 border-emerald-600 text-emerald-950': checkStatus === 'success'
                                     }">
                                    <button type="button"
                                            @click="handleCheckStatus()"
                                            :disabled="isChecking"
                                            class="w-full rounded-none px-5 py-3 font-mono font-bold text-xs uppercase tracking-wider text-white transition-colors flex items-center justify-center gap-2 cursor-pointer"
                                            :class="checkStatus === 'success' ? 'bg-ink hover:bg-electric' : 'bg-electric hover:bg-electricHover disabled:opacity-60'">
                                        <svg x-show="isChecking" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span x-text="buttonText"></span>
                                    </button>

                                    <div class="mt-3 text-xs">
                                        <template x-if="checkStatus === 'idle'">
                                            <p class="text-muted">Setelah pembayaran selesai di Midtrans, sistem otomatis mengaktifkan pesanan Anda.</p>
                                        </template>
                                        <template x-if="checkStatus === 'pending'">
                                            <div class="space-y-1.5 text-left bg-white p-3 border border-amber-400">
                                                <div class="font-mono font-bold text-amber-900 text-xs">Status: Menunggu Pembayaran</div>
                                                <p class="text-[11px] text-amber-800 leading-relaxed">Webhook dari Midtrans sedang ditunggu. Selesaikan pembayaran pada jendela Midtrans.</p>
                                                <div class="font-mono text-[11px] text-amber-950 pt-1 border-t border-amber-200">
                                                    Verifikasi otomatis: <span x-text="countdownText"></span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="checkStatus === 'success'">
                                            <div class="space-y-1 text-left bg-white p-3 border border-emerald-500">
                                                <div class="font-mono font-bold text-emerald-900 text-xs">Status: Pembayaran Berhasil Diterima</div>
                                                <p class="text-[11px] text-emerald-800">Transaksi Anda telah terverifikasi. Mengalihkan ke dashboard...</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Fallback Non-Midtrans VA Payment --}}
                        @if(empty($isMidtrans) && in_array($order->payment_method, ['bca_va', 'mandiri_va', 'bni_va', 'bri_va']))
                        <div class="text-center mb-8">
                            <div class="bg-creamDark/40 border border-hairline p-6 max-w-md mx-auto">
                                <h3 class="font-display font-bold text-lg text-ink mb-3">Virtual Account</h3>
                                <div class="bg-white border border-ink p-4 mb-4">
                                    <div class="font-mono text-[10px] uppercase text-muted mb-1">Nomor Virtual Account</div>
                                    <div class="font-bold text-xl font-mono text-electric" x-text="virtualAccountNumber"></div>
                                </div>
                                <p class="text-xs text-muted">Transfer ke nomor Virtual Account di atas melalui ATM, mobile banking, atau internet banking.</p>
                            </div>
                        </div>
                        @endif

                        {{-- Fallback Non-Midtrans E-Wallet --}}
                        @if(empty($isMidtrans) && in_array($order->payment_method, ['gopay', 'ovo', 'dana', 'shopeepay']))
                        <div class="text-center mb-8">
                            <div class="flex flex-col items-center">
                                <div class="qris-light-box w-32 h-32 mb-5 bg-white border-2 border-ink flex items-center justify-center p-4">
                                    @php
                                        $walletImages = [
                                            'gopay' => 'gopay.svg',
                                            'ovo' => 'ewallet_ovo.svg',
                                            'dana' => 'dana.svg',
                                            'shopeepay' => 'ewallet_shopeepay.svg'
                                        ];
                                    @endphp
                                    <img src="{{ asset('images/payments/' . ($walletImages[$order->payment_method] ?? 'qris.jpeg')) }}"
                                         alt="{{ strtoupper($order->payment_method) }}"
                                         class="max-w-full max-h-full object-contain">
                                </div>
                                <p class="text-xs text-muted mb-4">
                                    Buka aplikasi {{ strtoupper($order->payment_method) }} Anda dan scan QR code atau selesaikan tagihan tersedia.
                                </p>
                            </div>
                        </div>
                        @endif

                        {{-- Payment Instructions --}}
                        <div class="border border-hairline p-6 mb-6 bg-creamDark/30">
                            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-ink mb-4">Instruksi Pembayaran</h3>
                            <div class="space-y-4">
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 bg-ink text-cream flex items-center justify-center font-mono text-[10px] font-bold shrink-0">01</span>
                                    <div>
                                        <p class="font-display font-bold text-sm text-ink mb-0.5">Lakukan pembayaran</p>
                                        <p class="text-xs text-muted">
                                            @if($order->payment_method === 'qris')
                                                Scan QR code menggunakan aplikasi e-wallet atau mobile banking
                                            @elseif($order->payment_method === 'lynk')
                                                Buka tautan checkout Lynk.id dan selesaikan pembayaran via QRIS, Virtual Account, E-Wallet, atau Kartu
                                            @elseif(!empty($isMidtrans))
                                                Selesaikan pembayaran melalui jendela Midtrans menggunakan GoPay, QRIS, atau Virtual Account bank pilihan Anda
                                            @elseif(str_contains($order->payment_method, '_va'))
                                                Transfer ke nomor Virtual Account yang ditampilkan
                                            @else
                                                Gunakan aplikasi {{ strtoupper($order->payment_method) }} untuk melakukan pembayaran
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 bg-ink text-cream flex items-center justify-center font-mono text-[10px] font-bold shrink-0">02</span>
                                    <div>
                                        <p class="font-display font-bold text-sm text-ink mb-0.5">Verifikasi Otomatis</p>
                                        <p class="text-xs text-muted">Sistem otomatis mendeteksi mutasi pembayaran dalam 1-5 menit via webhook gateway.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 bg-ink text-cream flex items-center justify-center font-mono text-[10px] font-bold shrink-0">03</span>
                                    <div>
                                        <p class="font-display font-bold text-sm text-ink mb-0.5">Provisioning Server</p>
                                        <p class="text-xs text-muted">Setelah pembayaran lunas, instance VPS otomatis disiapkan dan kredensial dikirimkan ke email Anda.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="text-center space-y-3">
                            <a href="{{ route('order.payment.status', $order->id) }}"
                               class="rounded-none inline-block px-8 py-3.5 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                                Cek Status Pembayaran
                            </a>
                            <p class="text-xs text-muted">
                                Setelah membayar, sistem otomatis memverifikasi transaksi Anda. Anda juga dapat memeriksa status secara manual.
                            </p>

                            @if(!empty($devSimulateEnabled))
                                <form method="POST" action="{{ route('order.payment.dev-simulate', $order->id) }}"
                                      class="pt-4 border-t border-amber-400 mt-4">
                                    @csrf
                                    <div class="bg-amber-50 border border-amber-500 p-3 mb-3 text-left">
                                        <p class="font-mono text-xs font-bold text-amber-900 uppercase">[DEV MODE]</p>
                                        <p class="text-xs text-amber-800 mt-1">
                                            Tombol simulasi ini hanya aktif di environment lokal untuk testing. Di production, settlement wajib via webhook gateway.
                                        </p>
                                    </div>
                                    <button type="submit"
                                            class="w-full rounded-none px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-mono font-bold text-xs uppercase tracking-wider cursor-pointer">
                                        [DEV] Simulate Payment Settlement
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Order Summary --}}
            <div class="lg:col-span-1">
                <div class="sticky top-24 space-y-6">
                    <div class="bg-white border border-ink p-6 space-y-4">
                        <h3 class="font-display font-bold text-ink text-lg border-b border-hairline pb-3">Ringkasan Pesanan</h3>

                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between gap-2">
                                <span class="font-mono uppercase text-muted">VPS Name</span>
                                <span class="font-mono font-bold text-ink text-right">{{ $order->hostname ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="font-mono uppercase text-muted">Paket</span>
                                <span class="font-display font-bold text-ink text-right">{{ $order->vpsSpec->name }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="font-mono uppercase text-muted">Infrastruktur</span>
                                <span class="font-semibold text-ink text-right">{{ $order->provider_label }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="font-mono uppercase text-muted">Datacenter</span>
                                <span class="font-mono uppercase font-bold text-ink text-right">{{ $order->datacenter_location }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="font-mono uppercase text-muted">OS</span>
                                <span class="font-semibold text-ink text-right">{{ $order->os_label }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="font-mono uppercase text-muted">Stack</span>
                                <span class="font-semibold text-ink text-right">{{ $order->control_panel_label }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="font-mono uppercase text-muted">Periode</span>
                                <span class="font-bold text-ink text-right">
                                    @if($order->billing_cycle === 'annual') 12 Bulan (1 Tahun)
                                    @elseif($order->billing_cycle === 'semi_annual') 6 Bulan
                                    @elseif($order->billing_cycle === 'quarterly') 3 Bulan
                                    @else 1 Bulan @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-baseline border-t border-ink pt-3">
                                <span class="font-mono uppercase font-bold text-ink">Total Tagihan</span>
                                <span class="font-bold text-electric text-lg font-mono">Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="border-t border-hairline pt-4">
                            <div class="flex items-center gap-2 font-mono text-[11px] uppercase text-muted mb-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Batas waktu pembayaran</span>
                            </div>
                            <div class="bg-red-50 border border-red-400 p-3 text-center">
                                <p class="font-mono font-bold text-red-800 text-xs">
                                    {{ ($order->invoice && $order->invoice->due_at) ? $order->invoice->due_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : now()->addDay()->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                                </p>
                                <p class="text-[11px] text-red-700 mt-1">Pesanan dibatalkan otomatis setelah batas waktu</p>
                            </div>
                        </div>
                    </div>

                    {{-- Support Contact Card --}}
                    <div class="bg-white border border-hairline p-6">
                        <h4 class="font-mono text-xs font-bold uppercase tracking-wider text-ink mb-3">Butuh Bantuan Pembayaran?</h4>
                        <div class="space-y-3 text-xs text-muted">
                            <a href="{{ \App\Support\NomorWhatsApp::tautan() }}" target="_blank" rel="noopener" class="flex items-start gap-2.5 hover:text-electric transition-colors">
                                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                <div>
                                    <p class="font-bold text-ink">WhatsApp Support</p>
                                    <p class="font-mono">{{ \App\Support\NomorWhatsApp::tampil() }}</p>
                                </div>
                            </a>
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <div>
                                    <p class="font-bold text-ink">Email Support</p>
                                    <p class="font-mono">support@cravacloud.id</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Success Modal --}}
    <div x-show="showSuccessModal" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-ink/60 flex items-center justify-center z-50 p-4">
        <div class="bg-white border-2 border-ink max-w-md w-full p-6" @click.away="showSuccessModal = false">
            <div class="text-center mb-6">
                <div class="w-12 h-12 bg-emerald-600 text-white flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="font-display text-xl font-bold text-ink mb-2">Pembayaran Berhasil</h3>
                <p class="text-xs text-muted">Pembayaran Anda telah dikonfirmasi. Tim teknis sedang mempersiapkan server VPS Anda.</p>
            </div>
            <div class="space-y-2.5 text-xs mb-6 border-t border-b border-hairline py-4 font-mono">
                <div class="flex justify-between">
                    <span class="text-muted">Invoice</span>
                    <span class="font-bold text-ink">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted">Jumlah</span>
                    <span class="font-bold text-ink">Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted">Status</span>
                    <span class="font-bold text-emerald-700">LUNAS</span>
                </div>
            </div>
            <div class="text-center">
                <a href="{{ route('order.success', $order->id) }}" class="rounded-none block px-6 py-3 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors w-full">
                    Lihat Detail Pesanan
                </a>
                <p class="font-mono text-[10px] text-muted mt-3">Invoice telah dikirimkan ke email Anda</p>
            </div>
        </div>
    </div>
</div>

<script>
function paymentGateway() {
    return {
        isProcessing: false,
        showSuccessModal: false,
        virtualAccountNumber: this.generateVirtualAccount(),
        elapsedSeconds: {{ $elapsedSeconds }},
        checkStatus: 'idle',
        isChecking: false,
        timer: null,
        pollTimer: null,
        snapToken: '{{ $snapToken ?? '' }}',
        snapRedirectUrl: '{{ $snapRedirectUrl ?? '' }}',
        isMidtrans: {{ !empty($isMidtrans) ? 'true' : 'false' }},

        init() {
            this.timer = setInterval(() => {
                this.elapsedSeconds++;
            }, 1000);

            if (this.isMidtrans && this.snapToken) {
                setTimeout(() => {
                    this.payWithMidtrans();
                }, 800);
            }

            this.pollTimer = setInterval(() => {
                fetch('{{ route("order.payment.status.json", $order->id) }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.paid_at || ['paid', 'provisioning', 'active'].includes(data.status)) {
                        clearInterval(this.pollTimer);
                        this.checkStatus = 'success';
                        window.location.href = '{{ route("dashboard.index", ["payment_success" => 1, "order_id" => $order->id]) }}';
                    }
                })
                .catch(() => {});
            }, 4000);
        },

        payWithMidtrans() {
            if (!this.snapToken) {
                showAlert('Token pembayaran Midtrans tidak ditemukan. Silakan muat ulang halaman.', { icon: 'error' });
                return;
            }
            if (typeof window.snap === 'undefined') {
                if (this.snapRedirectUrl) {
                    window.open(this.snapRedirectUrl, '_blank');
                    return;
                }
                showAlert('SDK Midtrans gagal dimuat di browser Anda. Harap nonaktifkan ad-blocker atau muat ulang halaman.', { icon: 'error' });
                return;
            }

            window.snap.pay(this.snapToken, {
                onSuccess: () => {
                    this.checkStatus = 'success';
                    showAlert('Pembayaran berhasil dikonfirmasi! Mengalihkan ke dashboard...', { icon: 'success' });
                    setTimeout(() => {
                        window.location.href = '{{ route("dashboard.index", ["payment_success" => 1, "order_id" => $order->id]) }}';
                    }, 1200);
                },
                onPending: () => {
                    this.checkStatus = 'pending';
                    showAlert('Transaksi dibuat! Silakan selesaikan pembayaran sesuai instruksi.', { icon: 'info' });
                },
                onError: () => {
                    showAlert('Pembayaran gagal atau dibatalkan.', { icon: 'error' });
                },
                onClose: () => {}
            });
        },

        get countdownText() {
            const rem = Math.max(0, 60 - this.elapsedSeconds);
            if (rem > 0) {
                return rem + ' detik lagi';
            }
            return 'Siap diverifikasi';
        },

        get buttonText() {
            if (this.isChecking) return 'Memeriksa Mutasi...';
            if (this.checkStatus === 'success') return 'Buka Status Pesanan →';
            if (this.checkStatus === 'pending') return 'Cek Ulang Status Pembayaran';
            return 'Cek Status Pembayaran';
        },

        handleCheckStatus() {
            if (this.checkStatus === 'success') {
                window.location.href = '{{ route("dashboard.index", ["payment_success" => 1, "order_id" => $order->id]) }}';
                return;
            }

            this.isChecking = true;

            fetch('{{ route("order.payment.status.json", $order->id) }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.isChecking = false;
                if (data.paid_at || ['paid', 'provisioning', 'active'].includes(data.status)) {
                    this.checkStatus = 'success';
                    if (this.pollTimer) clearInterval(this.pollTimer);
                    showAlert('Pembayaran berhasil dikonfirmasi! Mengalihkan ke dashboard...', { icon: 'success', title: 'Berhasil' });
                    setTimeout(() => {
                        window.location.href = '{{ route("dashboard.index", ["payment_success" => 1, "order_id" => $order->id]) }}';
                    }, 1000);
                } else {
                    this.checkStatus = 'pending';
                    showAlert('Pembayaran belum terkonfirmasi oleh sistem atau admin. Silakan selesaikan pembayaran atau tunggu mutasi diverifikasi.', { icon: 'info', title: 'Menunggu Pembayaran' });
                }
            })
            .catch(() => {
                this.isChecking = false;
                showAlert('Gagal memeriksa status pembayaran. Silakan coba beberapa saat lagi.', { icon: 'error', title: 'Terjadi Kesalahan' });
            });
        },

        generateVirtualAccount() {
            const prefix = {
                'bca_va': '88012',
                'mandiri_va': '88888',
                'bni_va': '88123',
                'bri_va': '88234'
            }['{{ $order->payment_method }}'] || '88000';

            const random = Math.floor(Math.random() * 100000000).toString().padStart(8, '0');
            return prefix + random;
        },
    };
}
</script>
@endsection
