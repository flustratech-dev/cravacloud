<section id="ops-monitoring" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Metrik</span>
        <span class="text-xs font-semibold text-slate-500">Resource & Quota</span>
    </div>
    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
        Memantau Pemakaian Resource
    </h2>
    <p class="text-slate-600 text-sm leading-relaxed">
        Dashboard menampilkan spesifikasi paket (vCPU, RAM, disk) dan status layanan. Untuk melihat pemakaian langsung, jalankan perintah berikut di server:
    </p>
    <div class="bg-[#0B0F19] text-slate-300 p-4 rounded-xl font-mono-code text-xs space-y-1">
        <p><span class="text-emerald-400">$</span> htop      <span class="text-slate-500"># pemakaian CPU &amp; RAM per proses</span></p>
        <p><span class="text-emerald-400">$</span> free -h   <span class="text-slate-500"># sisa memori</span></p>
        <p><span class="text-emerald-400">$</span> df -h     <span class="text-slate-500"># sisa ruang disk</span></p>
    </div>
</section>
