<section id="api-shopee-process" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono-code bg-indigo-100 text-indigo-800 uppercase">POST</span>
        <span class="font-mono-code text-xs font-bold text-slate-900">/api/admin/shopee/process-order</span>
    </div>
    <h3 class="text-xl font-bold text-slate-900 tracking-tight">
        Eksekusi Otomasi Order Shopee
    </h3>
    <p class="text-slate-600 text-sm leading-relaxed">
        Endpoint webhook yang dipanggil oleh bot otomasi atau integrasi marketplace Shopee ketika pembeli menyelesaikan checkout voucher paket VPS.
    </p>

    <!-- Parameter Specification Table -->
    <div class="border border-slate-200 rounded-xl overflow-hidden bg-white text-xs">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Field Body</th>
                    <th class="px-4 py-3">Tipe</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-mono-code">
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">shopee_order_sn</td>
                    <td class="px-4 py-3 text-slate-600">string</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Nomor seri unik pesanan dari platform Shopee.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">buyer_username</td>
                    <td class="px-4 py-3 text-slate-600">string</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Username akun Shopee pembeli.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">vps_spec_id</td>
                    <td class="px-4 py-3 text-slate-600">integer</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">ID paket yang dipilih (1 s/d 6).</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">os</td>
                    <td class="px-4 py-3 text-slate-600">string</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Distribusi OS target: <code>ubuntu2404</code>, <code>debian12</code>, dll.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-slate-900 font-sans">control_panel</td>
                    <td class="px-4 py-3 text-slate-600">string</td>
                    <td class="px-4 py-3 text-slate-500 font-sans">Opsional</td>
                    <td class="px-4 py-3 text-slate-600 font-sans">Opsi: <code>coolify</code>, <code>dokploy</code>, <code>aapanel</code>, atau <code>none</code>.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
