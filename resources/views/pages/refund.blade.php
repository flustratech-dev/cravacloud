@extends('layouts.app', [
    'title' => 'CravaCloud Kebijakan Pengembalian Dana (Refund Policy)',
    'description' => 'Kebijakan pengembalian dana CravaCloud: syarat refund, jangka waktu pengajuan, layanan yang memenuhi syarat, dan cara mengajukannya.',
    'breadcrumbs' => [
        ['name' => 'Beranda', 'url' => url('/')],
        ['name' => 'Kebijakan Refund'],
    ],
])

@section('content')
<div x-data="{
    activeSection: 'pasal-1',
    scrollTo(id) {
        const el = document.getElementById(id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
            this.activeSection = id;
        }
    }
}" class="min-h-screen bg-cream text-ink selection:bg-electric selection:text-white">

    <!-- Top Sub-Header Bar (Legal Documentation Hub) -->
    <div class="bg-creamDark/90 backdrop-blur-md border-b border-hairline sticky top-20 z-30">
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 h-14 flex items-center justify-between">
            <div class="flex items-center space-x-3 overflow-x-auto no-scrollbar py-2">
                <span class="text-xs font-bold uppercase tracking-wider text-muted whitespace-nowrap">Portal Legal</span>
                <span class="text-hairline">/</span>
                <nav class="flex items-center space-x-1 sm:space-x-2 text-xs">
                    <a href="{{ route('terms') }}" class="px-2.5 py-1 rounded-none font-mono font-medium text-muted hover:text-ink hover:bg-white transition-colors whitespace-nowrap">
                        Terms of Service
                    </a>
                    <a href="{{ route('privacy') }}" class="px-2.5 py-1 rounded-none font-mono font-medium text-muted hover:text-ink hover:bg-white transition-colors whitespace-nowrap">
                        Privacy Policy
                    </a>
                    <a href="{{ route('refund') }}" class="px-2.5 py-1 rounded-none font-mono font-bold bg-electric text-white whitespace-nowrap">
                        Refund Policy
                    </a>
                </nav>
            </div>

            <div class="hidden sm:flex items-center space-x-3 shrink-0">
                <button onclick="window.print()" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-none border border-ink bg-white hover:bg-ink hover:text-white text-ink font-mono text-xs font-semibold transition-colors shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Dokumen</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Header Hero Banner -->
    <header class="bg-white border-b border-hairline py-10 sm:py-12">
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14">
            <div class="max-w-3xl">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-none font-mono text-[10px] font-bold uppercase tracking-wider bg-creamDark text-ink border border-hairline">
                        Dokumen Kebijakan Transaksi
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-none font-mono text-[10px] font-bold uppercase tracking-wider bg-emerald-950 text-emerald-300 border border-emerald-700">
                        Garansi Teknis 48 Jam
                    </span>
                </div>
                <h1 class="font-display text-2xl sm:text-4xl font-black uppercase tracking-tight text-ink">
                    Kebijakan Pengembalian Dana (Refund Policy)
                </h1>
                <p class="mt-3 text-sm text-muted leading-relaxed">
                    Ketentuan transparan pembatalan transaksi, syarat garansi kendala teknis awal, batasan non-refundable, serta prosedur pencairan dana bagi pelanggan CravaCloud.
                </p>

                <!-- Metadata Grid -->
                <div class="mt-6 pt-6 border-t border-hairline grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-muted block font-medium">Jendela Garansi</span>
                        <span class="font-semibold text-ink mt-0.5 block">48 Jam Sejak Aktivasi</span>
                    </div>
                    <div>
                        <span class="text-muted block font-medium">Estimasi Waktu Proses</span>
                        <span class="font-semibold text-ink mt-0.5 block">1 &ndash; 3 Hari Kerja Bank</span>
                    </div>
                    <div>
                        <span class="text-muted block font-medium">Metode Pengembalian</span>
                        <span class="font-semibold text-ink mt-0.5 block">Bank Lokal &amp; E-Wallet</span>
                    </div>
                    <div>
                        <span class="text-muted block font-medium">Identifikasi Dokumen</span>
                        <span class="font-mono font-semibold text-ink mt-0.5 block">REF-2026-V2</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Layout (Sidebar + Content Body) -->
    <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-10 sm:py-12">
        <div class="flex flex-col lg:flex-row gap-10">

            <!-- Left Sticky Sidebar Table of Contents -->
            <aside class="w-full lg:w-72 shrink-0">
                <div class="sticky top-36 space-y-6">
                    <div class="bg-white rounded-none border border-hairline p-4">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-muted mb-3 px-2">
                            Daftar Klausul Refund
                        </h2>
                        <nav class="space-y-1 text-xs">
                            <button @click="scrollTo('pasal-1')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                1. Prinsip Kepuasan Layanan
                            </button>
                            <button @click="scrollTo('pasal-2')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                2. Garansi Teknis 48 Jam
                            </button>
                            <button @click="scrollTo('pasal-3')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                3. Kondisi Non-Refundable
                            </button>
                            <button @click="scrollTo('pasal-4')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                4. Pesanan Marketplace Shopee
                            </button>
                            <button @click="scrollTo('pasal-5')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                5. Persyaratan Pengajuan Klaim
                            </button>
                            <button @click="scrollTo('pasal-6')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                6. Saluran &amp; Durasi Pencairan
                            </button>
                            <button @click="scrollTo('pasal-7')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                7. Biaya Administrasi Gateway
                            </button>
                            <button @click="scrollTo('pasal-8')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                8. Pencegahan Penyalahgunaan
                            </button>
                        </nav>
                    </div>

                    <!-- Billing Helpdesk Card -->
                    <div class="bg-white rounded-none border border-ink p-4 text-xs space-y-2.5">
                        <div class="font-bold text-ink flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span>Pusat Bantuan Billing</span>
                        </div>
                        <p class="text-muted leading-relaxed">
                            Konsultasikan kendala tagihan dan faktur Anda langsung ke tim finance:
                        </p>
                        <div class="space-y-1 font-mono text-[11px]">
                            <p class="text-ink bg-cream p-1.5 rounded-none border border-hairline">{{ \App\Support\MailIdentity::contactEmail() }}</p>
                            <a href="{{ \App\Support\NomorWhatsApp::tautan() }}" target="_blank" class="text-[#25D366] block font-semibold hover:underline">
                                WhatsApp Finance {{ \App\Support\NomorWhatsApp::lokal() }}
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Legal Article Body -->
            <main class="flex-1 min-w-0 space-y-12">

                <!-- Pasal 1 -->
                <section id="pasal-1" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 1
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Prinsip &amp; Komitmen Kepuasan Layanan
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            CravaCloud Indonesia berkomitmen menghadirkan solusi Cloud VPS, Dedicated Database, dan runtime AI Agent dengan performa komputasi terbaik dan ketersediaan tinggi.
                        </p>
                        <p>
                            Kami memahami bahwa dalam penerapan infrastruktur teknis, potensi kendala hardware host atau inkompatibilitas arsitektur dapat terjadi. Dokumen ini menjelaskan hak serta mekanisme pengembalian dana (refund) secara berkeadilan, transparan, dan terukur.
                        </p>
                    </div>
                </section>

                <!-- Pasal 2 -->
                <section id="pasal-2" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 2
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Garansi Teknis 48 Jam Pertama (First Provisioning Guarantee)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            Bagi setiap pemesanan unit server baru, Pelanggan berhak mengajukan permohonan pengembalian dana penuh (100% dari biaya sewa paket) dalam jangka waktu <strong>48 (empat puluh delapan) jam</strong> terhitung sejak kredensial server pertama kali diterbitkan, dengan kriteria teknis berikut:
                        </p>
                        <div class="overflow-x-auto my-3">
                            <table class="w-full text-left border-collapse border border-hairline text-xs">
                                <thead>
                                    <tr class="bg-creamDark text-ink">
                                        <th class="p-3 border border-hairline font-bold w-1/3">Kondisi Kendala Teknis</th>
                                        <th class="p-3 border border-hairline font-bold">Ambang Batas Verifikasi</th>
                                        <th class="p-3 border border-hairline font-bold">Status Hak Refund</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline">
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Kerusakan Hardware / Host Node</td>
                                        <td class="p-3 border border-hairline">Host hypervisor KVM mengalami kerusakan fisik yang gagal dipulihkan dalam 24 jam.</td>
                                        <td class="p-3 border border-hairline font-semibold text-emerald-700">Eligible (Disetujui Penuh)</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Ketidaksesuaian Spesifikasi</td>
                                        <td class="p-3 border border-hairline">Alokasi inti vCPU, RAM, atau kapasitas NVMe tidak sesuai dengan paket yang dipesan.</td>
                                        <td class="p-3 border border-hairline font-semibold text-emerald-700">Eligible (Disetujui Penuh)</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Kegagalan Routing Jaringan Publik</td>
                                        <td class="p-3 border border-hairline">IP publik yang diberikan terblokir permanen pada upstream gateway datacenter sejak awal penerbitan.</td>
                                        <td class="p-3 border border-hairline font-semibold text-emerald-700">Eligible (Ganti IP / Refund)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Pasal 3 -->
                <section id="pasal-3" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 3
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Kondisi Pengecualian (Non-Refundable Circumstances)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            Pengembalian dana secara tegas <strong>TIDAK BERLAKU</strong> dalam situasi-situasi berikut:
                        </p>
                        <ul class="space-y-2 pl-4 border-l-2 border-hairline text-xs">
                            <li>
                                <strong>Pelanggaran Acceptable Use Policy (AUP):</strong> Server yang disuspend atau diterminasi karena terdeteksi menjalankan aktivitas penambangan kripto (crypto mining), serangan DDoS, pengiriman spam, hosting botnet, atau materi ilegal.
                            </li>
                            <li>
                                <strong>Faktur Perpanjangan (Renewal):</strong> Pembayaran perpanjangan masa sewa untuk bulan kedua dan seterusnya tidak memenuhi syarat pengembalian dana, karena server telah dioperasikan pada periode sebelumnya.
                            </li>
                            <li>
                                <strong>Kesalahan Konfigurasi Internal Pengguna:</strong> Pengguna salah memilih varian Sistem Operasi (misal: memilih Ubuntu padahal membutuhkan Debian), salah mengatur iptables/UFW sehingga terkunci dari SSH, atau gagal mengompilasi aplikasi mandiri.
                            </li>
                            <li>
                                <strong>Inkompatibilitas Aplikasi Eksternal:</strong> Aplikasi atau script pihak ketiga milik Pengguna yang tidak berjalan karena membutuhkan dependensi proprietary di luar spesifikasi Linux standar.
                            </li>
                            <li>
                                <strong>Pengajuan Melewati Batas Waktu:</strong> Permohonan yang diajukan setelah melewati batas 48 jam sejak server aktif pertama kali.
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Pasal 4 -->
                <section id="pasal-4" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 4
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Ketentuan Transaksi Marketplace Shopee
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            4.1. Bagi transaksi pemesanan yang dilakukan melalui Toko Resmi Shopee CravaCloud, mekanisme pembatalan pesanan dan resolusi dana tunduk pada Syarat &amp; Ketentuan Perlindungan Pembeli (Garansi Shopee).
                        </p>
                        <p>
                            4.2. Apabila Anda mengalami kendala teknis pada 48 jam pertama untuk pesanan Shopee, Anda wajib berkomunikasi terlebih dahulu dengan Customer Care CravaCloud melalui chat resmi Shopee sebelum mengklik tombol "Pesanan Diterima".
                        </p>
                    </div>
                </section>

                <!-- Pasal 5 -->
                <section id="pasal-5" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 5
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Persyaratan &amp; Alur Pengajuan Klaim Refund
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            Pengajuan klaim pengembalian dana dilakukan melalui tahapan formal:
                        </p>
                        <ol class="list-decimal list-inside space-y-2 pl-2 text-xs text-ink">
                            <li>Buka <strong>Tiket Bantuan Dukungan Teknis</strong> pada Client Dashboard dengan kategori departemen <em>Billing &amp; Finance</em>.</li>
                            <li>Sertakan <strong>Nomor Faktur / Invoice ID</strong> (contoh: <code class="font-mono bg-creamDark px-1 py-0.5 rounded-none">INV-XXXX</code>) dan Alamat IP instans yang bersangkutan.</li>
                            <li>Jelaskan secara terperinci deskripsi kendala teknis yang dihadapi beserta tangkapan layar (screenshot) log galat diagnostik.</li>
                            <li>Tim teknis CravaCloud akan melakukan verifikasi forensik pada hypervisor dalam waktu maksimal 1x24 jam sejak tiket diterima.</li>
                        </ol>
                    </div>
                </section>

                <!-- Pasal 6 -->
                <section id="pasal-6" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 6
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Saluran Pencairan &amp; Estimasi Waktu Proses
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            6.1. Setelah permohonan pengembalian dana dinyatakan disetujui secara tertulis oleh tim Finance, dana akan ditransfer kembali ke rekening atau dompet digital asal Pelanggan dalam waktu <strong>1 hingga 3 hari kerja operasional perbankan</strong> (tidak termasuk hari Sabtu, Minggu, dan hari libur nasional).
                        </p>
                        <p>
                            6.2. Saluran pengembalian dana yang didukung mencakup:
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-xs text-ink">
                            <li><strong>Transfer Bank Nasional:</strong> Bank Central Asia (BCA), Bank Mandiri, Bank Rakyat Indonesia (BRI), Bank Negara Indonesia (BNI).</li>
                            <li><strong>Dompet Digital (E-Wallet):</strong> GoPay, OVO, DANA, ShopeePay.</li>
                        </ul>
                    </div>
                </section>

                <!-- Pasal 7 -->
                <section id="pasal-7" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 7
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Biaya Administrasi Transaksi Pihak Ketiga
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            Nilai dana yang dikembalikan adalah murni nilai sewa layanan yang diterima CravaCloud. Biaya transaksi pihak ketiga (MDR payment gateway QRIS / Virtual Account / biaya admin bank) yang dipotong langsung oleh pihak perbankan saat transaksi awal bersifat non-refundable dan berada di luar yurisdiksi kendali CravaCloud.
                        </p>
                    </div>
                </section>

                <!-- Pasal 8 -->
                <section id="pasal-8" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 8
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Pencegahan Penyalahgunaan Kebijakan (Fraud &amp; Abuse Prevention)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            Kebijakan pengembalian dana dirancang untuk melindungi hak pelanggan yang beritikad baik. CravaCloud berhak menolak permohonan refund dari pengguna yang terindikasi melakukan penyalahgunaan sistematis (churning), seperti berulang kali memesan server untuk mengeksploitasi bandwidth atau kuota komputasi singkat lalu meminta pengembalian dana.
                        </p>
                        <p>
                            Pengguna yang terbukti melakukan penyalahgunaan kebijakan refund dapat dikenai pemblokiran akun permanen dari platform CravaCloud.
                        </p>
                    </div>

                    <!-- Sign-off block -->
                    <div class="mt-8 pt-6 border-t border-hairline flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 text-xs text-muted">
                        <div>
                            <p class="font-normal text-ink">CravaCloud Indonesia</p>
                            <p class="text-muted mt-0.5">Departemen Keuangan, Penagihan &amp; Perlindungan Konsumen</p>
                        </div>
                        <div class="font-mono text-[11px] text-muted">
                            Dokumen Resmi: REF-2026-V2
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</div>
@endsection
