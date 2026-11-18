<section id="api-shopee-process" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2 py-0.5 rounded-none text-[10px] font-bold font-mono bg-indigo-100 text-indigo-800 uppercase">POST</span>
        <span class="font-mono text-xs font-bold text-ink">/api/admin/shopee/process-order</span>
    </div>
    <h3 class="text-xl font-bold text-ink tracking-tight">
        Eksekusi Otomasi Order Shopee
    </h3>
    <p class="text-muted text-sm leading-relaxed">
        Endpoint webhook yang dipanggil oleh bot otomasi atau integrasi marketplace Shopee ketika pembeli menyelesaikan checkout voucher paket VPS.
    </p>

    <!-- Parameter Specification Table -->
    <div class="border border-hairline rounded-none overflow-hidden bg-white text-xs">
        <table class="w-full text-left">
            <thead class="bg-cream text-muted font-semibold border-b border-hairline">
                <tr>
                    <th class="px-4 py-3">Field Body</th>
                    <th class="px-4 py-3">Tipe</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline font-mono">
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">shopee_order_sn</td>
                    <td class="px-4 py-3 text-muted">string</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-muted font-sans">Nomor seri unik pesanan dari platform Shopee.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">buyer_username</td>
                    <td class="px-4 py-3 text-muted">string</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-muted font-sans">Username akun Shopee pembeli.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">vps_spec_id</td>
                    <td class="px-4 py-3 text-muted">integer</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-muted font-sans">ID paket yang dipilih (1 s/d 6).</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">os</td>
                    <td class="px-4 py-3 text-muted">string</td>
                    <td class="px-4 py-3 text-rose-600 font-sans font-semibold">Wajib</td>
                    <td class="px-4 py-3 text-muted font-sans">Distribusi OS target: <code>ubuntu2404</code>, <code>debian12</code>, dll.</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold text-ink font-sans">control_panel</td>
                    <td class="px-4 py-3 text-muted">string</td>
                    <td class="px-4 py-3 text-muted font-sans">Opsional</td>
                    <td class="px-4 py-3 text-muted font-sans">Opsi: <code>coolify</code>, <code>dokploy</code>, <code>aapanel</code>, atau <code>none</code>.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
