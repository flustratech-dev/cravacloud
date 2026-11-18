<section id="security-ufw" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-none text-[10px] font-bold font-mono bg-electric text-white uppercase">Modul 04</span>
        <span class="text-xs font-semibold text-muted">Keamanan Server</span>
    </div>
    <h2 class="font-display text-2xl sm:text-3xl font-black text-ink uppercase tracking-tight">
        Firewall UFW & Port Security Policy
    </h2>
    <p class="text-muted text-sm leading-relaxed">
        Secara default pada instalasi Ubuntu dan Debian, Uncomplicated Firewall (UFW) dalam kondisi tidak aktif. Sangat direkomendasikan untuk mengaktifkannya dengan aturan akses minimal (<em>Principle of Least Privilege</em>):
    </p>

    <div class="rounded-none border border-ink bg-ink overflow-hidden text-xs">
        <div class="bg-ink px-4 py-2 border-b border-white/15 flex items-center justify-between">
            <span class="text-cream/70 font-mono text-[11px]">Konfigurasi Standar Firewall UFW</span>
            <button @click="copyToClipboard('sudo ufw default deny incoming\nsudo ufw default allow outgoing\nsudo ufw allow 22/tcp comment \'SSH Access\'\nsudo ufw allow 80/tcp comment \'HTTP Traffic\'\nsudo ufw allow 443/tcp comment \'HTTPS Traffic\'\nsudo ufw enable', 'ufw-setup')"
                    class="text-[11px] font-semibold text-cream/70 hover:text-white flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied['ufw-setup'] ? 'Tersalin!' : 'Salin'"></span>
            </button>
        </div>
        <div class="p-4 font-mono text-cream/90 leading-relaxed overflow-x-auto space-y-1">
            <p class="text-cream/60"># Set default policy: tolak seluruh akses masuk, izinkan keluar</p>
            <p><span class="text-emerald-400">$</span> sudo ufw default deny incoming</p>
            <p><span class="text-emerald-400">$</span> sudo ufw default allow outgoing</p>
            <p class="text-muted pt-2"># Izinkan SSH dan protokol Web sebelum mengaktifkan firewall</p>
            <p><span class="text-emerald-400">$</span> sudo ufw allow 22/tcp comment 'SSH Access'</p>
            <p><span class="text-emerald-400">$</span> sudo ufw allow 80/tcp comment 'HTTP Traffic'</p>
            <p><span class="text-emerald-400">$</span> sudo ufw allow 443/tcp comment 'HTTPS Traffic'</p>
            <p class="text-muted pt-2"># Aktifkan firewall</p>
            <p><span class="text-emerald-400">$</span> sudo ufw enable</p>
        </div>
    </div>
</section>
