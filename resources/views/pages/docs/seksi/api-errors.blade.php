<section id="api-errors" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Standar</span>
        <span class="text-xs font-semibold text-slate-500">HTTP Status & Error Codes</span>
    </div>
    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
        Format Respon & Standar Kode Error
    </h2>
    <p class="text-slate-600 text-sm leading-relaxed">
        API CravaCloud menggunakan kode status HTTP standar untuk mengindikasikan keberhasilan atau kegagalan request. Setiap kegagalan menyertakan objek JSON berisi pesan diagnostik yang jelas:
    </p>

    <div class="border border-slate-200 rounded-xl overflow-hidden bg-white text-xs">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Kode HTTP</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Penyebab / Deskripsi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-mono-code">
                <tr>
                    <td class="px-4 py-3 font-bold text-emerald-600">200 OK</td>
                    <td class="px-4 py-3 text-slate-800">Success</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Permintaan berhasil diproses dan mengembalikan data yang diminta.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-emerald-600">201 Created</td>
                    <td class="px-4 py-3 text-slate-800">Created</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Instance baru atau order baru berhasil dialokasikan.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-amber-600">400 Bad Request</td>
                    <td class="px-4 py-3 text-slate-800">Malformed Payload</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Sintaks body JSON tidak valid atau struktur tidak dikenali.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-rose-600">401 Unauthorized</td>
                    <td class="px-4 py-3 text-slate-800">Invalid Key</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Header <code>X-Admin-Key</code> tidak disertakan atau nilai token salah.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-amber-600">404 Not Found</td>
                    <td class="px-4 py-3 text-slate-800">Resource Missing</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">ID VPS atau nomor pesanan tidak ditemukan di database.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-amber-600">422 Unprocessable</td>
                    <td class="px-4 py-3 text-slate-800">Validation Error</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Gagal validasi parameter (misal: provider tidak cocok dengan paket).</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-rose-600">500 Server Error</td>
                    <td class="px-4 py-3 text-slate-800">Internal Failure</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Kendala teknis pada upstream datacenter API hypervisor.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
