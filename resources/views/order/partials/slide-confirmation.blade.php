<div class="border border-hairline bg-white p-5 sm:p-8 space-y-6">
    <div class="border-b border-hairline pb-4 flex items-center justify-between gap-4">
        <div>
            <span class="font-mono text-[10px] uppercase tracking-widest text-electric font-bold block mb-1">KONFIRMASI // RINGKASAN KONFIGURASI</span>
            <h2 class="font-display text-xl sm:text-2xl font-bold text-ink tracking-tight">Konfirmasi Pesanan</h2>
        </div>
        <span class="w-8 h-8 bg-electric text-white font-mono text-xs font-bold flex items-center justify-center shrink-0">02</span>
    </div>

    {{-- Summary Grid --}}
    <div class="bg-creamDark/40 border border-hairline p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
        <div>
            <span class="font-mono text-[10px] uppercase tracking-wider text-muted block mb-1">Hostname</span>
            <span class="font-mono font-bold text-ink text-sm" x-text="vpsName || '-'"></span>
        </div>
        <div>
            <span class="font-mono text-[10px] uppercase tracking-wider text-muted block mb-1">Paket VPS</span>
            <span class="font-display font-bold text-ink text-sm" x-text="currentSpec ? currentSpec.name + ' (' + currentSpec.cpu + ' Core / ' + currentSpec.ram + ' GB)' : '-'"></span>
        </div>
        <div>
            <span class="font-mono text-[10px] uppercase tracking-wider text-muted block mb-1">Datacenter</span>
            <span class="font-mono font-bold text-ink uppercase" x-text="datacenter === 'indonesia' ? 'Indonesia (JKT DCI-ID)' : 'Singapore (SIN-1 Edge)'"></span>
        </div>
        <div>
            <span class="font-mono text-[10px] uppercase tracking-wider text-muted block mb-1">OS &amp; Stack</span>
            <span class="font-semibold text-ink">
                <span x-text="operatingSystems[os] || (os === 'ubuntu2404' ? 'Ubuntu 24.04 LTS' : 'Ubuntu 22.04 LTS')"></span> · <span x-text="controlPanelLabel"></span>
            </span>
        </div>
    </div>

    {{-- Direct Checkout Payment Notice --}}
    <div class="p-4 sm:p-5 bg-white border border-hairline space-y-2.5">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-electric mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="square" stroke-linejoin="miter" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <div class="space-y-1">
                <h3 class="font-display font-bold text-ink text-sm">Alur Pembayaran Aman (Payment Gateway Otomatis)</h3>
                <p class="text-xs text-muted leading-relaxed">
                    Saat Anda menekan tombol <strong>Konfirmasi &amp; Bayar</strong>, invoice pembayaran resmi akan diterbitkan melalui gateway pembayaran aman. Pilih QRIS atau Virtual Account (Mandiri, BNI, BRI, Permata, CIMB Niaga), dan server diprovisioning otomatis begitu lunas.
                </p>
            </div>
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
                    Server CravaCloud diperuntukkan untuk aplikasi, situs web, AI agent, dan database yang sah. Dilarang keras menjalankan aktivitas berikut:
                </p>
            </div>
        </div>

        <ul class="text-xs text-ink/90 space-y-1.5 pl-4 list-disc marker:text-red-700">
            <li>Layanan VPN publik, open proxy, exit node, atau traffic relaying/tunneling tanpa izin;</li>
            <li>Scraping, crawling, atau automasi agresif tanpa persetujuan pemilik situs target;</li>
            <li>Pemrosesan agregator torrent, tracker, distribusi file hak cipta, dan bot abuse/spamming;</li>
            <li>Pengiriman email massal (spam), crypto mining, DDoS, scanning port, atau serangan siber.</li>
        </ul>

        <div class="p-3 bg-white border border-amber-500 text-xs text-amber-950 leading-relaxed">
            Pelanggaran dapat mengakibatkan <strong>terminasi instan tanpa pengembalian dana</strong> karena berpotensi memicu suspensi dari penyedia infrastruktur hulu. Backup data sepenuhnya menjadi tanggung jawab Anda.
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
