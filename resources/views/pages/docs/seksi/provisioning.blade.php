<section id="provisioning" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Alur Kerja</span>
        <span class="text-xs font-semibold text-slate-500">Aktivasi Server</span>
    </div>
    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
        Alur Aktivasi Server
    </h2>
    <p class="text-slate-600 text-sm leading-relaxed">
        Setiap server disiapkan langsung oleh tim CravaCloud setelah pembayaran terkonfirmasi. Urutannya sebagai berikut:
    </p>

    <div class="space-y-3 font-mono-code text-xs">
        <div class="p-3.5 rounded-lg border border-slate-200 bg-white flex items-start gap-3">
            <span class="w-6 h-6 rounded bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0">1</span>
            <div>
                <span class="font-bold text-slate-900 block font-sans text-xs">Penerimaan Pesanan & Validasi Pembayaran</span>
                <span class="text-slate-500 font-sans text-[11px]">Invoice diterbitkan. Setelah pembayaran terkonfirmasi (otomatis lewat gateway atau diverifikasi tim), status pesanan berubah menjadi <code class="text-emerald-700 bg-emerald-50 px-1 rounded">paid</code>.</span>
            </div>
        </div>

        <div class="p-3.5 rounded-lg border border-slate-200 bg-white flex items-start gap-3">
            <span class="w-6 h-6 rounded bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0">2</span>
            <div>
                <span class="font-bold text-slate-900 block font-sans text-xs">Server Disiapkan Tim</span>
                <span class="text-slate-500 font-sans text-[11px]">Tim CravaCloud menyiapkan server di infrastruktur penyedia (Tencent Cloud atau Lintasarta Cloudeka) sesuai paket dan lokasi yang Anda pilih. Pesanan tampil sebagai "sedang disiapkan" di dashboard.</span>
            </div>
        </div>

        <div class="p-3.5 rounded-lg border border-slate-200 bg-white flex items-start gap-3">
            <span class="w-6 h-6 rounded bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0">3</span>
            <div>
                <span class="font-bold text-slate-900 block font-sans text-xs">Pemasangan OS & Stack</span>
                <span class="text-slate-500 font-sans text-[11px]">Sistem operasi pilihan Anda dipasang. Jika memilih panel seperti Coolify atau Dokploy, tim memasangnya lalu mencantumkan alamat panelnya di dashboard.</span>
            </div>
        </div>

        <div class="p-3.5 rounded-lg border border-slate-200 bg-white flex items-start gap-3">
            <span class="w-6 h-6 rounded bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0">4</span>
            <div>
                <span class="font-bold text-slate-900 block font-sans text-xs">Serah Terima di Dashboard</span>
                <span class="text-slate-500 font-sans text-[11px]">IP publik, port SSH, dan password root (tersimpan terenkripsi) tampil di Client Portal, dan Anda menerima email pemberitahuan. Status server berubah menjadi <code class="text-emerald-700 bg-emerald-50 px-1 rounded">Aktif</code>.</span>
            </div>
        </div>
    </div>
</section>
