<div class="border border-hairline bg-white p-5 sm:p-8 space-y-6">
    <div class="border-b border-hairline pb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="font-display text-xl sm:text-2xl font-bold text-ink tracking-tight">Metode Pembayaran</h2>
            <p class="text-xs text-muted mt-1">Satu gerbang pembayaran instan dan aman untuk seluruh saluran pembayaran resmi.</p>
        </div>
        <span class="w-8 h-8 bg-electric text-white font-mono text-xs font-bold flex items-center justify-center shrink-0"
              x-text="isDirectCheckout ? '02' : '03'"></span>
    </div>

    {{-- Unified Payment Gateway Card --}}
    <label class="block p-5 sm:p-6 cursor-pointer transition-colors relative text-left select-none"
           :class="paymentMethod === 'online_payment' ? 'border-2 border-electric bg-cream' : 'border border-hairline hover:border-ink/50 bg-white'"
           @click="selectPayment('online_payment')">
        <input type="radio" name="payment_method" value="online_payment" x-model="paymentMethod" class="sr-only">

        {{-- Top Badges & Selector --}}
        <div class="flex items-center justify-between gap-2 mb-4">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 font-mono text-[10px] font-bold uppercase tracking-wider bg-ink text-cream">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Otomatis &amp; Instan
                </span>
                <span class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-900 border border-emerald-400">
                    Verifikasi 24 Jam
                </span>
            </div>
            <div class="w-5 h-5 border-2 flex items-center justify-center transition-colors shrink-0"
                 :class="paymentMethod === 'online_payment' ? 'border-electric bg-electric text-white' : 'border-ink/40 bg-white'">
                <svg x-show="paymentMethod === 'online_payment'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="square" stroke-linejoin="miter" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </div>

        <div class="mb-4">
            <h3 class="font-display text-lg font-bold text-ink">Pembayaran Otomatis Online (Midtrans)</h3>
            <p class="text-xs sm:text-sm text-muted mt-1 leading-relaxed">
                Bayar mudah dan cepat tanpa konfirmasi manual melalui Midtrans. Pilih QRIS (semua e-wallet &amp; mobile banking), Virtual Account BCA, Mandiri, BNI, BRI, Permata, CIMB Niaga, GoPay, ShopeePay, atau Kartu Kredit di jendela Snap resmi.
            </p>
        </div>

        {{-- Supported Channel Logos --}}
        <div class="p-4 bg-white border border-hairline space-y-2.5">
            <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-wider text-muted font-bold">
                <span>Saluran pembayaran didukung (Midtrans):</span>
                <span class="text-emerald-700">Terhubung Langsung</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @php
                    $channels = [
                        ['QRIS (Semua E-Wallet & Mobile Banking)', 'qris.svg', 'h-5 sm:h-6'],
                        ['Bank BCA VA', 'va_bca.svg', 'h-4 sm:h-5'],
                        ['Bank Mandiri VA', 'va_mandiri.svg', 'h-4 sm:h-5'],
                        ['Bank BNI VA', 'va_bni.svg', 'h-4 sm:h-5'],
                        ['Bank BRI VA', 'va_bri.svg', 'h-4 sm:h-5'],
                        ['CIMB Niaga VA', 'va_cimb.svg', 'h-4 sm:h-5'],
                        ['Bank Permata VA', 'va_permata.svg', 'h-4 sm:h-5'],
                        ['GoPay', 'gopay.svg', 'h-4 sm:h-5'],
                        ['ShopeePay', 'ewallet_shopeepay.svg', 'h-4 sm:h-5'],
                        ['Indomaret Retail', 'indomaret.svg', 'h-4 sm:h-5'],
                        ['Akulaku PayLater', 'akulaku.svg', 'h-4 sm:h-5'],
                    ];
                @endphp
                @foreach($channels as $c)
                    <div class="h-8 sm:h-9 px-2.5 sm:px-3 bg-white border border-hairline flex items-center justify-center"
                         title="{{ $c[0] }}">
                        <img src="{{ asset('images/payments/' . $c[1]) }}" alt="{{ $c[0] }}" class="{{ $c[2] }} max-w-[65px] object-contain">
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Feature Highlights --}}
        <div class="mt-4 pt-4 border-t border-hairline grid grid-cols-1 sm:grid-cols-3 gap-2.5 font-mono text-[11px] text-ink">
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>Verifikasi otomatis 24 jam</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>Tanpa upload bukti transfer</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>Server diprovisi seketika</span>
            </div>
        </div>
    </label>

    {{-- Security Notice --}}
    <div class="p-4 bg-creamDark/40 border border-hairline text-xs text-muted flex items-start gap-3">
        <svg class="w-5 h-5 text-electric shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="square" stroke-linejoin="miter" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <div class="space-y-1">
            <span class="font-display font-bold text-ink block">Standar Keamanan Transaksi &amp; Lisensi Resmi</span>
            <p class="leading-relaxed">
                Seluruh transaksi dilindungi enkripsi TLS 256-bit dan diproses melalui payment gateway berlisensi resmi Bank Indonesia. Saat menekan tombol <strong>Bayar Sekarang</strong>, jendela checkout resmi terbuka otomatis.
            </p>
        </div>
    </div>

    {{-- Mandatory Acceptable Use Policy & Terms Consent --}}
    <div class="p-4 sm:p-5 bg-creamDark/60 border border-ink space-y-4">
        <div class="flex items-start gap-2.5">
            <svg class="w-5 h-5 text-red-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="square" stroke-linejoin="miter" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-ink">Ketentuan Penggunaan Layanan</h3>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    Untuk melindungi infrastruktur bersama CravaCloud, layanan ini dilarang digunakan untuk aktivitas berikut:
                </p>
            </div>
        </div>

        <ul class="text-xs text-ink/90 space-y-1.5 pl-4 list-disc marker:text-red-700">
            <li>Layanan VPN publik, open proxy, exit node, atau traffic relaying tanpa izin tertulis;</li>
            <li>Scraping, crawling, atau pengumpulan data otomatis ke target pihak ketiga;</li>
            <li>Pemrosesan atau hosting agregator torrent, tracker, dan distribusi file ilegal;</li>
            <li>Bot abuse, spamming email massal, crypto mining, DDoS, dan scanning port.</li>
        </ul>

        <div class="p-3 bg-white border border-amber-500 text-xs text-amber-950 leading-relaxed">
            Pelanggaran dapat mengakibatkan <strong>terminasi instan tanpa pengembalian dana</strong> karena dampak pada tingkat akun penyedia infrastruktur hulu. Pencadangan data sepenuhnya menjadi tanggung jawab Anda.
        </div>

        <label class="flex items-start gap-3 cursor-pointer pt-1">
            <input type="checkbox" name="terms_accepted" value="1" required
                   x-model="termsAccepted"
                   class="w-4 h-4 mt-0.5 rounded-none border-ink text-electric focus:ring-electric shrink-0">
            <span class="text-xs text-ink leading-relaxed">
                Saya telah membaca dan menyetujui
                <a href="{{ route('terms') }}" target="_blank" rel="noopener"
                   class="font-bold text-electric underline underline-offset-2">Ketentuan Layanan</a> dan
                <a href="{{ route('refund') }}" target="_blank" rel="noopener"
                   class="font-bold text-electric underline underline-offset-2">Kebijakan Pengembalian Dana</a>
                CravaCloud, termasuk seluruh larangan penggunaan di atas.
                <span class="block mt-1 font-mono text-[11px] text-muted">
                    Versi naskah: {{ config('legal.terms_version') }}
                </span>
            </span>
        </label>

        @error('terms_accepted')
            <p class="font-mono text-xs font-bold text-red-700">{{ $message }}</p>
        @enderror
    </div>
</div>
