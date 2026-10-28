<section id="stack-coolify" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Modul 03</span>
        <span class="text-xs font-semibold text-slate-500">Control Panel</span>
    </div>
    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
        Coolify Platform Deployment
    </h2>
    <p class="text-slate-600 text-sm leading-relaxed">
        <strong>Coolify</strong> adalah alternatif self-hosted paling populer untuk Heroku, Netlify, dan Vercel. Ketika Anda memilih stack Coolify pada saat checkout, skrip inisialisasi CravaCloud otomatis mengonfigurasi Docker Engine, Traefik reverse proxy, dan instans Coolify terbaru.
    </p>

    <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-3 text-xs">
        <span class="font-bold text-slate-900 block">Langkah Akses Coolify Pertama Kali:</span>
        <ol class="list-decimal list-inside space-y-1.5 text-slate-600">
            <li>Buka peramban web dan tuju URL: <code class="font-mono-code bg-slate-100 px-1 py-0.5 rounded text-slate-900">http://IP_VPS_ANDA:8000</code>.</li>
            <li>Lengkapi formulir pendaftaran akun administrator master (Root Admin).</li>
            <li>Tambahkan Git Repository (GitHub, GitLab, atau Git kustom) untuk mengaktifkan otomatisasi CI/CD zero-downtime.</li>
            <li>Konfigurasikan Domain Publik di menu Server Settings dengan mengarahkan DNS A Record ke IP VPS CravaCloud Anda.</li>
        </ol>
    </div>

    <!-- Terminal Code Snippet for Manual Installation -->
    <div class="rounded-xl border border-slate-800 bg-slate-950 overflow-hidden text-xs">
        <div class="bg-slate-900 px-4 py-2 border-b border-slate-800 flex items-center justify-between">
            <span class="text-slate-400 font-mono-code text-[11px]">Instalasi Manual Coolify (Jika Memilih Clean OS)</span>
            <button @click="copyToClipboard('curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash', 'coolify-install')"
                    class="text-[11px] font-semibold text-slate-400 hover:text-white flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied['coolify-install'] ? 'Tersalin!' : 'Salin'"></span>
            </button>
        </div>
        <div class="p-4 font-mono-code text-slate-300">
            <span class="text-emerald-400">$</span> curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
        </div>
    </div>
</section>
