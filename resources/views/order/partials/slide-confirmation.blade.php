<div class="space-y-6">
    {{-- Header Slide Konfirmasi --}}
    <div class="border border-slate-200 rounded-2xl p-5 sm:p-6 bg-white shadow-xs">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-semibold flex items-center justify-center" x-text="isDirectCheckout ? '2' : '3'"></span>
            <h2 class="font-bold text-slate-900 text-lg">Konfirmasi Pembayaran</h2>
        </div>
        <p class="text-sm text-slate-600">
            Periksa kembali detail pesanan, rincian biaya, dan selesaikan pembayaran instan Anda.
        </p>
    </div>

    {{-- Hidden payment method default to online_payment --}}
    <input type="hidden" name="payment_method" value="online_payment" x-model="paymentMethod">

    {{-- Card 1: Ringkasan Spesifikasi Server --}}
    <div class="border border-slate-200 rounded-2xl p-5 sm:p-6 bg-slate-50/70 shadow-xs space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Ringkasan Konfigurasi Layanan</h3>
            <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-white border border-slate-200 text-slate-700"
                  x-text="isDatabasePackage ? 'Managed Database' : (isAiPackage ? 'AI Combo' : 'Cloud VPS')"></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block mb-0.5">Hostname / VPS Name:</span>
                <span class="font-bold font-mono-code text-slate-900" x-text="vpsName || '-'"></span>
            </div>
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block mb-0.5">Paket &amp; Resource:</span>
                <span class="font-bold text-slate-900" x-text="currentSpec ? (currentSpec.name + ' (' + currentSpec.cpu + ' Core / ' + currentSpec.ram + ' GB RAM / ' + currentSpec.disk + ' GB NVMe)') : '-'"></span>
            </div>
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block mb-0.5">Datacenter &amp; Provider:</span>
                <span class="font-bold text-slate-900" x-text="(provider === 'tencent' ? 'Tencent Cloud' : 'Cloudeka by Lintasarta') + ' · ' + (datacenter === 'indonesia' ? 'Jakarta, ID' : 'Singapore')"></span>
            </div>
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block mb-0.5">Sistem Operasi / Stack:</span>
                <span class="font-bold text-slate-900" x-text="isDatabasePackage ? ('Engine: ' + dbEngineLabel + ' (' + (dbManager === 'cloudbeaver' ? 'CloudBeaver Web GUI' : 'CLI Only') + ')') : ((os === 'ubuntu2404' ? 'Ubuntu 24.04' : 'Ubuntu 22.04') + ' · ' + controlPanelLabel)"></span>
            </div>
        </div>
    </div>

    {{-- Card 2: Jaminan Keamanan & Informasi Transaksi --}}
    <div class="border border-slate-200 rounded-2xl p-4 sm:p-5 bg-white space-y-2 text-xs text-slate-600 shadow-xs">
        <div class="flex items-center gap-2 font-semibold text-slate-800">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span>Transaksi Aman &amp; Terverifikasi Otomatis</span>
        </div>
        <ul class="space-y-1.5 pl-6 list-disc text-slate-500">
            <li>Pilihan metode pembayaran (QRIS, VA Bank, Minimarket, atau PayLater) dapat langsung dipilih di halaman checkout pembayaran aman setelah menekan tombol <strong>Bayar Sekarang</strong>.</li>
            <li>Invoice resmi dan konfirmasi pembayaran akan otomatis dikirimkan ke alamat email Anda.</li>
            <li>Server langsung disiapkan secara otomatis oleh sistem kami segera setelah pembayaran berhasil diverifikasi.</li>
        </ul>
    </div>

    {{-- Card 3: Persetujuan Ketentuan Penggunaan (WAJIB).
         Persetujuan ini dicatat ke tabel terms_acceptances beserta versi naskah,
         waktu, dan alamat IP. Validasi ulang dilakukan di sisi server. --}}
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
