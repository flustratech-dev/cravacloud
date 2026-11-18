<section id="ops-monitoring" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-none text-[10px] font-bold font-mono bg-electric text-white uppercase">Metrik</span>
        <span class="text-xs font-semibold text-muted">Resource & Quota</span>
    </div>
    <h2 class="font-display text-xl sm:text-2xl font-bold text-ink uppercase tracking-tight">
        Memantau Pemakaian Resource
    </h2>
    <p class="text-muted text-sm leading-relaxed">
        Dashboard menampilkan spesifikasi paket (vCPU, RAM, disk) dan status layanan. Untuk melihat pemakaian langsung, jalankan perintah berikut di server:
    </p>
    <div class="bg-ink text-cream/90 p-4 rounded-none border border-ink font-mono text-xs space-y-1">
        <p><span class="text-emerald-400">$</span> htop      <span class="text-cream/60"># pemakaian CPU &amp; RAM per proses</span></p>
        <p><span class="text-emerald-400">$</span> free -h   <span class="text-cream/60"># sisa memori</span></p>
        <p><span class="text-emerald-400">$</span> df -h     <span class="text-cream/60"># sisa ruang disk</span></p>
    </div>
</section>
