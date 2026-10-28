<div class="border border-slate-200 rounded-2xl p-4 sm:p-7 bg-white shadow-xs space-y-6">
    <div class="flex items-center gap-3">
        <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-semibold flex items-center justify-center" x-text="isDirectCheckout ? '2' : '3'"></span>
        <div>
            <h2 class="font-bold text-slate-900 text-lg">Metode Pembayaran</h2>
            <p class="text-xs text-slate-500 mt-0.5">Satu gerbang pembayaran instan dan aman untuk seluruh saluran pembayaran resmi.</p>
        </div>
    </div>

    {{-- Kartu Utama Terpadu: Pembayaran Otomatis Online (Instan) --}}
    <label class="block p-5 sm:p-6 rounded-2xl border-2 cursor-pointer transition-all relative text-left select-none bg-slate-50/60 shadow-xs"
           :class="paymentMethod === 'online_payment' ? 'border-black bg-slate-50 ring-2 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'"
           @click="selectPayment('online_payment')">
        <input type="radio" name="payment_method" value="online_payment" x-model="paymentMethod" class="sr-only">

        {{-- Top Badges & Selector --}}
        <div class="flex items-center justify-between gap-2 mb-4">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-black text-white">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Otomatis &amp; Instan
                </span>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                    Verifikasi 24 Jam
                </span>
            </div>
            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all shrink-0"
                 :class="paymentMethod === 'online_payment' ? 'border-black bg-black text-white' : 'border-slate-300 bg-white'">
                <svg x-show="paymentMethod === 'online_payment'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
            </div>
        </div>

        {{-- Header Info --}}
        <div class="mb-4">
            <h3 class="text-base sm:text-lg font-bold text-slate-900">Pembayaran Otomatis Online (Instan)</h3>
            <p class="text-xs sm:text-sm text-slate-600 mt-1 leading-relaxed">
                Bayar mudah dan cepat tanpa perlu konfirmasi manual. Anda dapat bebas memilih metode pembayaran (QRIS semua bank/e-wallet, Virtual Account Mandiri, BNI, BRI, Permata, CIMB Niaga, Minimarket Indomaret, serta AstraPay &amp; Akulaku) langsung pada halaman checkout resmi yang aman.
            </p>
        </div>

        {{-- Gallery of Supported Channels / Logos --}}
        <div class="p-3.5 sm:p-4 rounded-xl bg-white border border-slate-200/90 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between text-[11px] text-slate-500 font-medium">
                <span>Saluran pembayaran yang didukung:</span>
                <span class="text-emerald-700 font-bold text-[10px] uppercase">Terhubung Langsung</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                @php
                    $channels = [
                        ['QRIS (BCA, Livin, GoPay, OVO, ShopeePay, Dana)', 'qris.svg', 'h-5 sm:h-6'],
                        ['Bank Mandiri VA', 'va_mandiri.svg', 'h-4 sm:h-5'],
                        ['Bank BNI VA', 'va_bni.svg', 'h-4 sm:h-5'],
                        ['Bank BRI VA', 'va_bri.svg', 'h-4 sm:h-5'],
                        ['CIMB Niaga VA', 'va_cimb.svg', 'h-4 sm:h-5'],
                        ['Bank Permata VA', 'va_permata.svg', 'h-4 sm:h-5'],
                        ['Indomaret Retail', 'indomaret.svg', 'h-4 sm:h-5'],
                        ['AstraPay E-Wallet', 'astrapay.svg', 'h-4 sm:h-5'],
                        ['Akulaku PayLater', 'akulaku.svg', 'h-4 sm:h-5'],
                    ];
                @endphp
                @foreach($channels as $c)
                    <div class="h-8 sm:h-9 px-2.5 sm:px-3 bg-white border border-slate-200 rounded-lg flex items-center justify-center shadow-2xs"
                         title="{{ $c[0] }}">
                        <img src="{{ asset('images/payments/' . $c[1]) }}" alt="{{ $c[0] }}" class="{{ $c[2] }} max-w-[65px] object-contain">
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Feature Highlights --}}
        <div class="mt-4 pt-4 border-t border-slate-200/80 grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs text-slate-600">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Verifikasi otomatis 24 jam</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Tanpa upload bukti transfer</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Server diprovisi seketika</span>
            </div>
        </div>
    </label>

    {{-- Keamanan & Jaminan Transaksi --}}
    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex items-start gap-3">
        <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <div class="space-y-0.5">
            <span class="font-bold text-slate-900 block">Standar Keamanan Transaksi &amp; Lisensi Resmi</span>
            <p class="leading-relaxed">
                Seluruh transaksi dilindungi enkripsi SSL 256-bit dan diproses melalui payment gateway berlisensi resmi Bank Indonesia. Saat menekan tombol <strong>Bayar Sekarang</strong> di bawah, invoice resmi yang aman akan terbuka untuk menyelesaikan pembayaran.
            </p>
        </div>
    </div>

    {{-- Persetujuan Ketentuan Penggunaan Layanan (WAJIB) --}}
    <div class="border border-slate-300 rounded-2xl p-5 sm:p-6 bg-slate-50 shadow-xs">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 004.99 19z"/>
            </svg>
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">Ketentuan Penggunaan Layanan</h3>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed mb-3">
            Layanan ini <strong>dilarang</strong> digunakan untuk: serangan siber dan DDoS, spam dan phishing,
            penambangan kripto, konten ilegal dan judi online, pemindaian port agresif,
            <strong>layanan VPN dan proxy</strong>, <strong>scraping dan crawling</strong>,
            <strong>agregator torrent</strong>, serta <strong>bot dan skrip otomatis</strong> yang menyalahi
            ketentuan pihak ketiga.
        </p>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 mb-4">
            <p class="text-[11px] text-amber-900 leading-relaxed">
                Pelanggaran dapat mengakibatkan <strong>terminasi instan tanpa pengembalian dana</strong>, dan
                berdampak pada seluruh pelanggan lain karena pembatasan dapat dikenakan di tingkat akun
                penyedia infrastruktur hulu. Pencadangan data sepenuhnya menjadi tanggung jawab Anda.
            </p>
        </div>

        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="terms_accepted" value="1" required
                   x-model="termsAccepted"
                   class="w-4 h-4 mt-0.5 rounded border-slate-400 text-black focus:ring-black shrink-0">
            <span class="text-xs text-slate-700 leading-relaxed">
                Saya telah membaca dan menyetujui
                <a href="{{ route('terms') }}" target="_blank" rel="noopener"
                   class="font-bold text-slate-900 underline underline-offset-2">Ketentuan Layanan</a> dan
                <a href="{{ route('refund') }}" target="_blank" rel="noopener"
                   class="font-bold text-slate-900 underline underline-offset-2">Kebijakan Pengembalian Dana</a>
                CravaCloud, termasuk seluruh larangan penggunaan di atas.
                <span class="block mt-1 text-[11px] text-slate-500 font-mono-code">
                    Versi naskah: {{ config('legal.terms_version') }}
                </span>
            </span>
        </label>

        @error('terms_accepted')
            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>