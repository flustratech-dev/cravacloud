@extends('layouts.app', ['title' => 'Pembayaran — CravaCloud'])

@section('content')
@if(!empty($isMidtrans) && !empty($snapJsUrl) && !empty($snapClientKey))
    <script src="{{ $snapJsUrl }}" data-client-key="{{ $snapClientKey }}"></script>
@endif
<div class="py-12 bg-slate-50 min-h-screen" x-data="paymentGateway()">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Back to Checkout --}}
        <a href="{{ route('checkout', $order->vps_spec_id) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-black transition-colors mb-8">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Kembali ke Checkout
        </a>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left: Payment Details --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    {{-- Header --}}
                    <div class="bg-black text-white p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h1 class="text-xl font-bold">Pembayaran {{ $order->payment_method_name }}</h1>
                                    <p class="text-sm text-white/80">Selesaikan pembayaran untuk mengaktifkan VPS Anda</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-white/60">Nomor Invoice</div>
                                <div class="font-bold font-mono-code">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Content --}}
                    <div class="p-6">
                        {{-- Lynk.id Payment --}}
                        @if($order->payment_method === 'lynk')
                        <div class="text-center mb-8 flex flex-col items-center justify-center">
                            {{-- Lynk Logo Container --}}
                            <div class="light-isolated w-48 h-20 mx-auto flex items-center justify-center p-3 bg-white dark:bg-white border border-slate-200 rounded-xl shadow-2xs mb-4">
                                <img src="{{ asset('images/payments/lynk.svg') }}" alt="Lynk.id Checkout" class="max-h-12 max-w-full object-contain">
                            </div>

                            <h2 class="text-lg font-bold text-slate-900 mb-1">Bayar via Lynk.id Gateway</h2>
                            <p class="text-xs text-slate-500 max-w-md mx-auto mb-4">
                                Selesaikan transaksi Anda dengan mudah melalui sistem pembayaran resmi Lynk.id (mendukung QRIS, VA Bank, GoPay, OVO, DANA, ShopeePay, dan Kartu Debit/Kredit).
                            </p>

                            {{-- Nominal Tagihan Sesuai Order --}}
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 max-w-sm w-full mx-auto text-center space-y-1 mb-5">
                                <span class="text-xs text-slate-500 block">Total Nominal Tagihan:</span>
                                <div class="text-2xl font-black font-mono-code text-slate-900">
                                    Rp {{ number_format($order->amount, 0, ',', '.') }}
                                </div>
                            </div>

                            {{-- Direct Lynk Button --}}
                            @php
                                $lynkCheckoutUrl = !empty($order->vpsSpec->payment_url) ? $order->vpsSpec->payment_url : null;
                            @endphp

                            @if($lynkCheckoutUrl)
                                <div class="w-full max-w-sm mx-auto mb-4">
                                    <a href="{{ $lynkCheckoutUrl }}" target="_blank"
                                       class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-lg bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white text-sm font-bold shadow-sm transition-all">
                                        <span>Buka Halaman Checkout Lynk.id</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    <p class="text-[11px] text-slate-400 mt-1.5">Tautan aman checkout Lynk.id akan terbuka di tab baru</p>
                                </div>
                            @endif

                            {{-- Section Cek Status Pembayaran --}}
                            <div class="w-full max-w-sm mx-auto">
                                <div class="p-4 rounded-xl border transition-all text-center"
                                     :class="{
                                         'bg-slate-50 border-slate-200': checkStatus === 'idle',
                                         'bg-amber-50 border-amber-300 text-amber-900': checkStatus === 'pending',
                                         'bg-emerald-50 border-emerald-300 text-emerald-900': checkStatus === 'success'
                                     }">
                                    
                                    {{-- Tombol Cek Status --}}
                                    <button type="button" 
                                            @click="handleCheckStatus()" 
                                            :disabled="isChecking"
                                            class="w-full px-5 py-3 rounded-lg font-bold text-sm text-white transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                                            :class="checkStatus === 'success' ? 'bg-black hover:bg-neutral-800' : 'bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60'">
                                        <svg x-show="isChecking" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg x-show="!isChecking && checkStatus !== 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        <svg x-show="checkStatus === 'success'" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span x-text="buttonText"></span>
                                    </button>

                                    {{-- Info Text di bawah tombol --}}
                                    <div class="mt-3 text-xs">
                                        <template x-if="checkStatus === 'idle'">
                                            <p class="text-slate-500">
                                                Setelah Anda menyelesaikan pembayaran di Lynk.id, klik tombol di atas untuk sinkronisasi status server.
                                            </p>
                                        </template>

                                        <template x-if="checkStatus === 'pending'">
                                            <div class="space-y-1.5 text-left bg-amber-100/80 p-3 rounded-lg border border-amber-200">
                                                <div class="flex items-center gap-1.5 font-bold text-amber-900 text-xs">
                                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>Status: Menunggu Pembayaran (Pending)</span>
                                                </div>
                                                <p class="text-[11px] text-amber-800 leading-relaxed">
                                                    Webhook dari Lynk.id belum diterima. Silakan selesaikan pembayaran di halaman Lynk.id.
                                                </p>
                                                <div class="text-[11px] text-amber-900 font-semibold pt-0.5 border-t border-amber-200">
                                                    Verifikasi otomatis: <span class="font-mono-code text-amber-950" x-text="countdownText"></span>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="checkStatus === 'success'">
                                            <div class="space-y-1 text-left bg-emerald-100/80 p-3 rounded-lg border border-emerald-200">
                                                <div class="flex items-center gap-1.5 font-bold text-emerald-900 text-xs">
                                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>Status: Pembayaran Berhasil Diterima!</span>
                                                </div>
                                                <p class="text-[11px] text-emerald-800 leading-relaxed">
                                                    Transaksi Lynk.id Anda telah terverifikasi. Mengalihkan ke dashboard...
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- QRIS Payment (Centered & Prominent) --}}
                        @if(in_array($order->payment_method, ['qris']))
                        <div class="text-center mb-8 flex flex-col items-center justify-center">
                            {{-- QR Code Image (Wajib tetap putih agar kamera scanner mobile banking dapat mendeteksi QR) --}}
                            <div class="qris-light-box w-72 h-72 sm:w-80 sm:h-80 mx-auto flex items-center justify-center p-4 bg-white dark:bg-white rounded-xl shadow-xs">
                                <img src="{{ $qrisDataUri }}" alt="QRIS Payment CravaCloud" class="max-w-full max-h-full object-contain">
                            </div>

                            {{-- Nominal Tagihan Sesuai Order (Centered) --}}
                            <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200 max-w-sm w-full mx-auto text-center space-y-1">
                                <span class="text-xs text-slate-500 block">Total Pembayaran Sesuai Tagihan:</span>
                                <div class="text-2xl font-black font-mono-code text-slate-900">
                                    Rp {{ number_format($order->amount, 0, ',', '.') }}
                                </div>
                                <button type="button" 
                                        @click="navigator.clipboard.writeText('{{ $order->amount }}'); showAlert('Nominal tagihan disalin: Rp {{ number_format($order->amount, 0, ',', '.') }}', { icon: 'success' })" 
                                        class="text-xs text-[#4A6FA5] hover:text-black font-semibold inline-flex items-center gap-1.5 pt-1 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Salin Nominal Tagihan</span>
                                </button>
                            </div>

                            {{-- Button Unduh / Buka QRIS Full --}}
                            <div class="mt-3 flex items-center justify-center gap-2">
                                <a href="{{ $qrisDataUri }}" target="_blank" download="qris-cravacloud-order-{{ $order->id }}.svg"
                                   class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 transition-colors shadow-2xs">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <span>Unduh / Buka QRIS</span>
                                </a>
                            </div>

                            {{-- Section Cek Status Pembayaran (Tepat di Bawah QRIS) --}}
                            <div class="mt-6 w-full max-w-sm mx-auto">
                                <div class="p-4 rounded-xl border transition-all text-center"
                                     :class="{
                                         'bg-slate-50 border-slate-200': checkStatus === 'idle',
                                         'bg-amber-50 border-amber-300 text-amber-900': checkStatus === 'pending',
                                         'bg-emerald-50 border-emerald-300 text-emerald-900': checkStatus === 'success'
                                     }">
                                    
                                    {{-- Tombol Cek Status --}}
                                    <button type="button" 
                                            @click="handleCheckStatus()" 
                                            :disabled="isChecking"
                                            class="w-full px-5 py-3 rounded-lg font-bold text-sm text-white transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                                            :class="checkStatus === 'success' ? 'bg-black hover:bg-neutral-800' : 'bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60'">
                                        <svg x-show="isChecking" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg x-show="!isChecking && checkStatus !== 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        <svg x-show="checkStatus === 'success'" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span x-text="buttonText"></span>
                                    </button>

                                    {{-- Info Text di bawah tombol --}}
                                    <div class="mt-3 text-xs">
                                        <template x-if="checkStatus === 'idle'">
                                            <p class="text-slate-500">
                                                Klik tombol di atas setelah Anda menyelesaikan pembayaran melalui aplikasi e-wallet / mobile banking.
                                            </p>
                                        </template>

                                        <template x-if="checkStatus === 'pending'">
                                            <div class="space-y-1.5 text-left bg-amber-100/80 p-3 rounded-lg border border-amber-200">
                                                <div class="flex items-center gap-1.5 font-bold text-amber-900 text-xs">
                                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>Status: Menunggu Pembayaran (Pending)</span>
                                                </div>
                                                <p class="text-[11px] text-amber-800 leading-relaxed">
                                                    Pembayaran belum terdeteksi pada sistem mutasi. Silakan selesaikan proses scan & bayar di aplikasi e-wallet Anda.
                                                </p>
                                                <div class="text-[11px] text-amber-900 font-semibold pt-0.5 border-t border-amber-200">
                                                    Verifikasi otomatis: <span class="font-mono-code text-amber-950" x-text="countdownText"></span>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="checkStatus === 'success'">
                                            <div class="space-y-1 text-left bg-emerald-100/80 p-3 rounded-lg border border-emerald-200">
                                                <div class="flex items-center gap-1.5 font-bold text-emerald-900 text-xs">
                                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>Status: Pembayaran Berhasil Diterima!</span>
                                                </div>
                                                <p class="text-[11px] text-emerald-800 leading-relaxed">
                                                    Transaksi QRIS Anda telah diterima dan masuk antrean aktivasi server. Mengalihkan ke halaman status...
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- Supported Apps Logos (Centered) --}}
                            <div class="space-y-2 mt-5 text-center">
                                <p class="text-xs text-slate-500">Mendukung semua aplikasi mobile banking &amp; e-wallet:</p>
                                <div class="flex flex-wrap items-center justify-center gap-2 max-w-md mx-auto">
                                    @foreach(['va_bca' => 'BCA', 'va_mandiri' => 'Mandiri', 'va_bri' => 'BRI', 'va_bni' => 'BNI', 'gopay' => 'GoPay', 'ewallet_ovo' => 'OVO', 'dana' => 'DANA', 'ewallet_shopeepay' => 'ShopeePay'] as $file => $app)
                                        <div class="light-isolated w-11 h-7 bg-white dark:bg-white border border-slate-200 rounded-md flex items-center justify-center p-1 shadow-2xs" title="{{ $app }}">
                                            <img src="{{ asset('images/payments/' . $file . '.svg') }}" alt="{{ $app }}" class="max-h-4 max-w-full object-contain">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Midtrans Snap Payment (Active Gateway) --}}
                        @if(!empty($isMidtrans))
                        <div class="text-center mb-8 flex flex-col items-center justify-center">
                            {{-- Midtrans Logo Container --}}
                            <div class="light-isolated w-48 h-16 mx-auto flex items-center justify-center p-3 bg-white dark:bg-white border border-slate-200 rounded-xl shadow-2xs mb-4">
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

                            <h2 class="text-lg font-bold text-slate-900 mb-1">
                                Pembayaran {{ $order->payment_method_name }} via Midtrans
                            </h2>
                            <p class="text-xs text-slate-500 max-w-md mx-auto mb-4">
                                Transaksi diproses secara instan dan terverifikasi otomatis melalui payment gateway resmi Midtrans (mendukung GoPay, QRIS, dan Virtual Account Bank).
                            </p>

                            {{-- Nominal Tagihan Sesuai Order --}}
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 max-w-sm w-full mx-auto text-center space-y-1 mb-5">
                                <span class="text-xs text-slate-500 block">Total Nominal Tagihan:</span>
                                <div class="text-2xl font-black font-mono-code text-slate-900">
                                    Rp {{ number_format($order->amount, 0, ',', '.') }}
                                </div>
                            </div>

                            @if(!empty($snapToken))
                                {{-- Tombol Utama: Bayar via Midtrans Pop-up --}}
                                <div class="w-full max-w-sm mx-auto space-y-2.5 mb-5">
                                    <button type="button" @click="payWithMidtrans()"
                                            class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm transition-all cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        <span>Buka Jendela Pembayaran Midtrans</span>
                                    </button>

                                    @if(!empty($snapRedirectUrl))
                                        <a href="{{ $snapRedirectUrl }}" target="_blank"
                                           class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition-all">
                                            <span>Buka di Tab Baru (Halaman Web Midtrans)</span>
                                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    @endif
                                    <p class="text-[11px] text-slate-400">Jendela pembayaran Midtrans otomatis terbuka. Klik tombol di atas jika belum muncul.</p>
                                </div>
                            @elseif(!empty($snapError))
                                <div class="w-full max-w-sm mx-auto mb-5 p-4 rounded-xl bg-amber-50 border border-amber-300 text-left">
                                    <div class="flex items-center gap-2 font-bold text-amber-900 text-xs mb-1">
                                        <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>Konfigurasi Gateway Belum Selesai</span>
                                    </div>
                                    <p class="text-xs text-amber-800 leading-relaxed">
                                        {{ $snapError }}
                                    </p>
                                    <p class="text-[11px] text-amber-700 mt-2">
                                        Admin CravaCloud: Pastikan Server Key dan Client Key sudah diisi di panel admin dan mode (Produksi/Sandbox) sesuai.
                                    </p>
                                </div>
                            @endif

                            {{-- Section Cek Status Pembayaran --}}
                            <div class="w-full max-w-sm mx-auto">
                                <div class="p-4 rounded-xl border transition-all text-center"
                                     :class="{
                                         'bg-slate-50 border-slate-200': checkStatus === 'idle',
                                         'bg-amber-50 border-amber-300 text-amber-900': checkStatus === 'pending',
                                         'bg-emerald-50 border-emerald-300 text-emerald-900': checkStatus === 'success'
                                     }">
                                    
                                    {{-- Tombol Cek Status --}}
                                    <button type="button" 
                                            @click="handleCheckStatus()" 
                                            :disabled="isChecking"
                                            class="w-full px-5 py-3 rounded-lg font-bold text-sm text-white transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                                            :class="checkStatus === 'success' ? 'bg-black hover:bg-neutral-800' : 'bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60'">
                                        <svg x-show="isChecking" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg x-show="!isChecking && checkStatus !== 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        <svg x-show="checkStatus === 'success'" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span x-text="buttonText"></span>
                                    </button>

                                    {{-- Info Text --}}
                                    <div class="mt-3 text-xs">
                                        <template x-if="checkStatus === 'idle'">
                                            <p class="text-slate-500">
                                                Setelah Anda menyelesaikan pembayaran di Midtrans, sistem akan otomatis mendeteksi dan mengaktifkan pesanan Anda.
                                            </p>
                                        </template>

                                        <template x-if="checkStatus === 'pending'">
                                            <div class="space-y-1.5 text-left bg-amber-100/80 p-3 rounded-lg border border-amber-200">
                                                <div class="flex items-center gap-1.5 font-bold text-amber-900 text-xs">
                                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>Status: Menunggu Pembayaran</span>
                                                </div>
                                                <p class="text-[11px] text-amber-800 leading-relaxed">
                                                    Webhook dari Midtrans sedang ditunggu. Silakan selesaikan pembayaran di jendela Midtrans.
                                                </p>
                                                <div class="text-[11px] text-amber-900 font-semibold pt-0.5 border-t border-amber-200">
                                                    Verifikasi otomatis: <span class="font-mono-code text-amber-950" x-text="countdownText"></span>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="checkStatus === 'success'">
                                            <div class="space-y-1 text-left bg-emerald-100/80 p-3 rounded-lg border border-emerald-200">
                                                <div class="flex items-center gap-1.5 font-bold text-emerald-900 text-xs">
                                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>Status: Pembayaran Berhasil Diterima!</span>
                                                </div>
                                                <p class="text-[11px] text-emerald-800 leading-relaxed">
                                                    Transaksi Anda telah terverifikasi. Mengalihkan ke dashboard...
                                                </p>
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
                            <div class="bg-slate-50 border border-slate-200 rounded-lg p-8 max-w-md mx-auto">
                                <div class="w-16 h-16 mx-auto mb-4 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="font-bold text-lg text-slate-900 mb-2">Virtual Account</h3>
                                <div class="bg-white border border-slate-300 rounded-lg p-4 mb-4">
                                    <div class="text-xs text-slate-500 mb-1">Nomor Virtual Account</div>
                                    <div class="font-bold text-xl font-mono-code text-slate-900" x-text="virtualAccountNumber"></div>
                                </div>
                                <p class="text-sm text-slate-600">
                                    Transfer ke nomor Virtual Account di atas melalui ATM, mobile banking, atau internet banking
                                </p>
                            </div>
                        </div>
                        @endif

                        {{-- Fallback Non-Midtrans E-Wallet Payment --}}
                        @if(empty($isMidtrans) && in_array($order->payment_method, ['gopay', 'ovo', 'dana', 'shopeepay']))
                        <div class="text-center mb-8">
                            <div class="flex flex-col items-center">
                                <div class="qris-light-box w-32 h-32 mb-6 bg-white dark:bg-white border-2 border-slate-300 rounded-lg flex items-center justify-center p-4">
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
                                <p class="text-sm text-slate-600 mb-4">
                                    Buka aplikasi {{ strtoupper($order->payment_method) }} Anda dan scan QR code atau gunakan metode pembayaran yang tersedia
                                </p>
                            </div>
                        </div>
                        @endif

                        {{-- Payment Instructions --}}
                        <div class="border border-slate-200 rounded-lg p-6 mb-8">
                            <h3 class="font-semibold text-slate-900 mb-4">Instruksi Pembayaran</h3>
                            <div class="space-y-4">
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>
                                    <div>
                                        <p class="font-medium text-sm text-slate-900 mb-1">Lakukan pembayaran</p>
                                        <p class="text-xs text-slate-600">
                                            @if($order->payment_method === 'qris')
                                                Scan QR code menggunakan aplikasi e-wallet atau mobile banking
                                            @elseif($order->payment_method === 'lynk')
                                                Buka tautan checkout Lynk.id dan selesaikan pembayaran via QRIS, Virtual Account, E-Wallet, atau Kartu
                                            @elseif(!empty($isMidtrans))
                                                Selesaikan pembayaran melalui jendela Midtrans menggunakan GoPay, QRIS, atau Virtual Account bank yang Anda pilih
                                            @elseif(str_contains($order->payment_method, '_va'))
                                                Transfer ke nomor Virtual Account yang ditampilkan
                                            @else
                                                Gunakan aplikasi {{ strtoupper($order->payment_method) }} untuk melakukan pembayaran
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>
                                    <div>
                                        <p class="font-medium text-sm text-slate-900 mb-1">Tunggu konfirmasi</p>
                                        <p class="text-xs text-slate-600">
                                            Sistem akan otomatis mendeteksi pembayaran dalam 1-5 menit
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>
                                    <div>
                                        <p class="font-medium text-sm text-slate-900 mb-1">Server di-setup</p>
                                        <p class="text-xs text-slate-600">
                                            Setelah pembayaran terkonfirmasi, tim teknis akan setup server Anda (1-2 jam kerja)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="text-center space-y-3">
                            <a href="{{ route('order.payment.status', $order->id) }}"
                               class="inline-block px-8 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors">
                                Cek Status Pembayaran
                            </a>
                            <p class="text-xs text-slate-500">
                                Setelah membayar, sistem akan mendeteksi otomatis via payment gateway.
                                Anda juga bisa cek status manual di halaman ini.
                            </p>

                            @if(!empty($devSimulateEnabled))
                                {{-- DEV ONLY: Simulasi pembayaran, hanya muncul kalau APP_ENV=local + APP_DEV_SIMULATE_PAYMENT=true --}}
                                <form method="POST" action="{{ route('order.payment.dev-simulate', $order->id) }}"
                                      class="pt-4 border-t border-amber-200 mt-4">
                                    @csrf
                                    <div class="bg-amber-50 border border-amber-300 rounded-lg p-3 mb-3 text-left">
                                        <p class="text-xs font-bold text-amber-800">⚠️ DEV MODE</p>
                                        <p class="text-xs text-amber-700 mt-1">
                                            Tombol simulasi ini hanya aktif di environment lokal untuk keperluan testing.
                                            Di production, konfirmasi pembayaran WAJIB via webhook gateway.
                                        </p>
                                    </div>
                                    <button type="submit"
                                            class="w-full px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm">
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
                <div class="sticky top-24">
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 space-y-5 mb-6">
                        <h3 class="font-semibold text-slate-900 text-base">Ringkasan Pesanan</h3>
                        
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">VPS Name</span>
                                <span class="font-medium text-slate-900 font-mono-code">{{ $order->hostname ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Paket</span>
                                <span class="font-medium text-slate-900">{{ $order->vpsSpec->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Provider</span>
                                <span class="font-medium text-slate-900">{{ $order->provider_label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Datacenter</span>
                                <span class="font-medium text-slate-900 capitalize">{{ $order->datacenter_location }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">OS</span>
                                <span class="font-medium text-slate-900">{{ $order->os_label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Stack</span>
                                <span class="font-medium text-slate-900">{{ $order->control_panel_label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Periode</span>
                                <span class="font-medium text-slate-900 font-semibold">
                                    @if($order->billing_cycle === 'annual')
                                        12 Bulan (1 Tahun)
                                    @elseif($order->billing_cycle === 'semi_annual')
                                        6 Bulan
                                    @elseif($order->billing_cycle === 'quarterly')
                                        3 Bulan
                                    @else
                                        1 Bulan
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between border-t border-slate-200 pt-3">
                                <span class="text-slate-500">Total Pembayaran</span>
                                <span class="font-bold text-slate-900 text-lg font-mono-code">Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <div class="flex items-center gap-2 text-sm text-slate-600 mb-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Batas waktu pembayaran:</span>
                            </div>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
                                <p class="font-bold text-red-700 text-sm">
                                    {{ ($order->invoice && $order->invoice->due_at) ? $order->invoice->due_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : now()->addDay()->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                                </p>
                                <p class="text-xs text-red-600 mt-1">Pesanan akan dibatalkan otomatis setelah batas waktu</p>
                            </div>
                        </div>
                    </div>

                    {{-- Support Info --}}
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
                        <h4 class="font-semibold text-slate-900 text-sm mb-3">Butuh Bantuan?</h4>
                        <div class="space-y-3 text-xs text-slate-600">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-slate-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <div>
                                    <p class="font-medium text-slate-700">WhatsApp Support</p>
                                    <p class="text-slate-500">+62 812-3456-7890</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-slate-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                <div>
                                    <p class="font-medium text-slate-700">Email Support</p>
                                    <p class="text-slate-500">support@cravacloud.id</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Success Modal --}}
    <div x-show="showSuccessModal" x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6" @click.away="showSuccessModal = false">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Pembayaran Berhasil!</h3>
                <p class="text-sm text-slate-600">
                    Pembayaran Anda telah dikonfirmasi. Tim teknis sedang mempersiapkan server VPS Anda.
                </p>
            </div>
            <div class="space-y-3 text-sm mb-6">
                <div class="flex justify-between">
                    <span class="text-slate-500">Invoice:</span>
                    <span class="font-mono-code font-semibold">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Jumlah:</span>
                    <span class="font-bold font-mono-code">Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Status:</span>
                    <span class="font-semibold text-emerald-600">LUNAS</span>
                </div>
            </div>
            <div class="text-center">
                <a href="{{ route('order.success', $order->id) }}" class="inline-block px-6 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold transition-colors w-full">
                    Lihat Detail Pesanan
                </a>
                <p class="text-xs text-slate-500 mt-3">
                    Invoice telah dikirim ke email Anda
                </p>
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
        checkStatus: 'idle', // 'idle' | 'pending' | 'success'
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

            // Auto-trigger Midtrans Snap jika metode Midtrans dan token tersedia
            if (this.isMidtrans && this.snapToken) {
                setTimeout(() => {
                    this.payWithMidtrans();
                }, 800);
            }

            // Background polling: otomatis periksa jika webhook masuk atau admin approve
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
                onSuccess: (result) => {
                    this.checkStatus = 'success';
                    showAlert('Pembayaran berhasil dikonfirmasi! Mengalihkan ke dashboard...', { icon: 'success' });
                    setTimeout(() => {
                        window.location.href = '{{ route("dashboard.index", ["payment_success" => 1, "order_id" => $order->id]) }}';
                    }, 1200);
                },
                onPending: (result) => {
                    this.checkStatus = 'pending';
                    showAlert('Transaksi dibuat! Silakan selesaikan pembayaran sesuai instruksi.', { icon: 'info' });
                },
                onError: (result) => {
                    showAlert('Pembayaran gagal atau dibatalkan.', { icon: 'error' });
                },
                onClose: () => {
                    // Popup ditutup oleh pelanggan
                }
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
            .catch(err => {
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
