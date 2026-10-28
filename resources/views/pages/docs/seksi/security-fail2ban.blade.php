<section id="security-fail2ban" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Intrusion Prevention</span>
        <span class="text-xs font-semibold text-slate-500">Fail2ban Daemon</span>
    </div>
    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
        Fail2ban & Proteksi Brute Force
    </h2>
    <p class="text-slate-600 text-sm leading-relaxed">
        Fail2ban memantau log sistem secara berkelanjutan. Ketika alamat IP tertentu mengalami 3-5 kegagalan autentikasi berturut-turut, Fail2ban langsung menyuntikkan aturan DROP pada tabel iptables/nftables selama durasi ban yang ditentukan (misal: 24 jam).
    </p>

    <div class="rounded-xl border border-slate-800 bg-slate-950 overflow-hidden text-xs">
        <div class="bg-slate-900 px-4 py-2 border-b border-slate-800 flex items-center justify-between">
            <span class="text-slate-400 font-mono-code text-[11px]">Instalasi & Status Fail2ban</span>
            <button @click="copyToClipboard('sudo apt update && sudo apt install fail2ban -y\nsudo systemctl enable --now fail2ban\nsudo fail2ban-client status sshd', 'fail2ban-setup')"
                    class="text-[11px] font-semibold text-slate-400 hover:text-white flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied['fail2ban-setup'] ? 'Tersalin!' : 'Salin'"></span>
            </button>
        </div>
        <div class="p-4 font-mono-code text-slate-300 leading-relaxed overflow-x-auto">
            <p><span class="text-emerald-400">$</span> sudo apt update && sudo apt install fail2ban -y</p>
            <p><span class="text-emerald-400">$</span> sudo systemctl enable --now fail2ban</p>
            <p><span class="text-emerald-400">$</span> sudo fail2ban-client status sshd</p>
        </div>
    </div>
</section>
