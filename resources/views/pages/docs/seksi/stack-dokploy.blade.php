<section id="stack-dokploy" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-none text-[10px] font-bold font-mono bg-electric text-white uppercase">App Platform</span>
        <span class="text-xs font-semibold text-muted">Modern PaaS</span>
    </div>
    <h2 class="font-display text-xl sm:text-2xl font-bold text-ink uppercase tracking-tight">
        Dokploy Modern PaaS Deployment
    </h2>
    <p class="text-muted text-sm leading-relaxed">
        <strong>Dokploy</strong> dirancang untuk kemudahan manajemen aplikasi microservices berbasis container, database cluster (PostgreSQL, MySQL, Redis, MongoDB), serta integrasi Docker Compose berlapis dengan dashboard visual yang minimalis dan sangat hemat sumber daya RAM.
    </p>

    <div class="rounded-none border border-ink bg-ink overflow-hidden text-xs">
        <div class="bg-ink px-4 py-2 border-b border-white/15 flex items-center justify-between">
            <span class="text-cream/70 font-mono text-[11px]">Setup Dokploy (Port 3000)</span>
            <button @click="copyToClipboard('curl -sSL https://dokploy.com/setup.sh | sh', 'dokploy-install')"
                    class="text-[11px] font-semibold text-cream/70 hover:text-white flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied['dokploy-install'] ? 'Tersalin!' : 'Salin'"></span>
            </button>
        </div>
        <div class="p-4 font-mono text-cream/90">
            <span class="text-emerald-400">$</span> curl -sSL https://dokploy.com/setup.sh | sh
        </div>
    </div>
</section>
