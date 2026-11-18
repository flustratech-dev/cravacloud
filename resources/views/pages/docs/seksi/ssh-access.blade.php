<section id="ssh-access" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-none text-[10px] font-bold font-mono bg-electric text-white uppercase">Akses Remote</span>
        <span class="text-xs font-semibold text-muted">Terminal Shell</span>
    </div>
    <h2 class="font-display text-xl sm:text-2xl font-bold text-ink uppercase tracking-tight">
        Akses SSH & Manajemen Kredensial
    </h2>
    <p class="text-muted text-sm leading-relaxed">
        Anda mendapatkan hak akses root penuh (<code class="font-mono text-xs bg-creamDark px-1.5 py-0.5 rounded-none">sudo / root</code>) melalui Secure Shell (SSH) protokol versi 2. Kredensial awal diberikan pada dashboard pelanggan.
    </p>

    <!-- Code Snippet Box with Copy Button -->
    <div class="rounded-none border border-ink bg-ink overflow-hidden text-xs">
        <div class="bg-ink px-4 py-2.5 border-b border-white/15 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500/80"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                <span class="text-[11px] font-mono text-cream/70 ml-2">bash &bull; terminal</span>
            </div>
            <button @click="copyToClipboard('ssh root@103.xxx.xxx.xxx', 'ssh-login')"
                    class="text-[11px] font-semibold text-cream/70 hover:text-white flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied['ssh-login'] ? 'Tersalin!' : 'Salin'"></span>
            </button>
        </div>
        <div class="p-4 font-mono text-cream/90 leading-relaxed overflow-x-auto">
            <p class="text-cream/60"># Login standar menggunakan password root awal</p>
            <p><span class="text-emerald-400">$</span> ssh root@103.xxx.xxx.xxx</p>
            <br>
            <p class="text-cream/60"># Jika Anda menggunakan private key (.pem / .id_ed25519)</p>
            <p><span class="text-emerald-400">$</span> ssh -i ~/.ssh/id_ed25519 root@103.xxx.xxx.xxx</p>
        </div>
    </div>
</section>
