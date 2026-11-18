<section id="api-auth" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-none text-[10px] font-bold font-mono bg-electric text-white uppercase">Modul 06</span>
        <span class="text-xs font-semibold text-muted">API Reference</span>
    </div>
    <h2 class="font-display text-2xl sm:text-3xl font-black text-ink uppercase tracking-tight">
        Autentikasi & Security Header API
    </h2>
    <p class="text-muted text-sm leading-relaxed">
        REST API CravaCloud memungkinkan otomasi deployment dan sinkronisasi status instance dengan sistem eksternal, e-commerce, atau bot internal Anda. Setiap permintaan wajib menyertakan token autentikasi rahasia melalui HTTP header:
    </p>

    <div class="border border-hairline rounded-none overflow-hidden bg-white text-xs">
        <table class="w-full text-left">
            <thead class="bg-cream text-muted font-semibold border-b border-hairline">
                <tr>
                    <th class="px-4 py-3">Header Name</th>
                    <th class="px-4 py-3">Format Nilai</th>
                    <th class="px-4 py-3">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline font-mono">
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">X-Admin-Key</td>
                    <td class="px-4 py-3 text-electric">cv_sec_k9f83n2x9...</td>
                    <td class="px-4 py-3 text-muted font-sans">Kunci akses rahasia master untuk backend integrations.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">Accept</td>
                    <td class="px-4 py-3 text-ink">application/json</td>
                    <td class="px-4 py-3 text-muted font-sans">Wajib diset ke JSON payload response.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">Content-Type</td>
                    <td class="px-4 py-3 text-ink">application/json</td>
                    <td class="px-4 py-3 text-muted font-sans">Dibutuhkan untuk seluruh request bertipe POST / PUT.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
