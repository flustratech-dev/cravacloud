<section id="api-auth" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Modul 06</span>
        <span class="text-xs font-semibold text-slate-500">API Reference</span>
    </div>
    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
        Autentikasi & Security Header API
    </h2>
    <p class="text-slate-600 text-sm leading-relaxed">
        REST API CravaCloud memungkinkan otomasi deployment dan sinkronisasi status instance dengan sistem eksternal, e-commerce, atau bot internal Anda. Setiap permintaan wajib menyertakan token autentikasi rahasia melalui HTTP header:
    </p>

    <div class="border border-slate-200 rounded-xl overflow-hidden bg-white text-xs">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Header Name</th>
                    <th class="px-4 py-3">Format Nilai</th>
                    <th class="px-4 py-3">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-mono-code">
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">X-Admin-Key</td>
                    <td class="px-4 py-3 text-[#4A6FA5]">cv_sec_k9f83n2x9...</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Kunci akses rahasia master untuk backend integrations.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">Accept</td>
                    <td class="px-4 py-3 text-slate-700">application/json</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Wajib diset ke JSON payload response.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">Content-Type</td>
                    <td class="px-4 py-3 text-slate-700">application/json</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Dibutuhkan untuk seluruh request bertipe POST / PUT.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
