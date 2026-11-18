@extends('layouts.app', [
    'title' => 'CravaCloud Ketentuan Layanan (Terms of Service)',
    'description' => 'Ketentuan Layanan CravaCloud: hak dan kewajiban pengguna, batas penggunaan wajar, prosedur penangguhan, serta tanggung jawab penyedia infrastruktur.',
    'breadcrumbs' => [
        ['name' => 'Beranda', 'url' => url('/')],
        ['name' => 'Ketentuan Layanan'],
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
                    <a href="{{ route('terms') }}" class="px-2.5 py-1 rounded-none font-mono font-bold bg-electric text-white whitespace-nowrap">
                        Terms of Service
                    </a>
                    <a href="{{ route('privacy') }}" class="px-2.5 py-1 rounded-none font-mono font-medium text-muted hover:text-ink hover:bg-white transition-colors whitespace-nowrap">
                        Privacy Policy
                    </a>
                    <a href="{{ route('refund') }}" class="px-2.5 py-1 rounded-none font-mono font-medium text-muted hover:text-ink hover:bg-white transition-colors whitespace-nowrap">
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
                        Dokumen Hukum Resmi
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-none font-mono text-[10px] font-bold uppercase tracking-wider bg-emerald-950 text-emerald-300 border border-emerald-700">
                        Status: Berlaku Efektif
                    </span>
                </div>
                <h1 class="font-display text-2xl sm:text-4xl font-black uppercase tracking-tight text-ink">
                    Ketentuan Layanan (Terms of Service)
                </h1>
                <p class="mt-3 text-sm text-muted leading-relaxed">
                    Perjanjian hukum mengikat antara pengguna dan CravaCloud Indonesia terkait penyediaan, pemanfaatan, serta batas tanggung jawab layanan infrastruktur Cloud VPS, Dedicated Database, dan runtime AI Agent.
                </p>

                <!-- Metadata Grid -->
                <div class="mt-6 pt-6 border-t border-hairline grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-muted block font-medium">Entitas Penyedia</span>
                        <span class="font-normal text-ink mt-0.5 block">PT Flustra Finances Artha (CravaCloud Indonesia)</span>
                    </div>
                    <div>
                        <span class="text-muted block font-medium">Revisi Terakhir</span>
                        <span class="font-semibold text-ink mt-0.5 block">
                            {{ \Carbon\Carbon::parse(config('legal.terms_effective_date'))->translatedFormat('d F Y') }}
                        </span>
                    </div>
                    <div>
                        <span class="text-muted block font-medium">Yurisdiksi Hukum</span>
                        <span class="font-semibold text-ink mt-0.5 block">Republik Indonesia</span>
                    </div>
                    <div>
                        <span class="text-muted block font-medium">Identifikasi Versi</span>
                        <span class="font-mono font-semibold text-ink mt-0.5 block">{{ config('legal.terms_version') }}</span>
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
                            Daftar Klausul Perjanjian
                        </h2>
                        <nav class="space-y-1 text-xs">
                            <button @click="scrollTo('pasal-1')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                1. Definisi &amp; Ruang Lingkup
                            </button>
                            <button @click="scrollTo('pasal-2')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                2. Akun &amp; Kredensial Root SSH
                            </button>
                            <button @click="scrollTo('pasal-3')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                3. Tanggung Jawab Operasional
                            </button>
                            <button @click="scrollTo('pasal-4')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                4. Acceptable Use Policy (AUP)
                            </button>
                            <button @click="scrollTo('pasal-5')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                5. Penagihan &amp; Siklus Terminasi
                            </button>
                            <button @click="scrollTo('pasal-6')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                6. Kebijakan Pencadangan (Backup)
                            </button>
                            <button @click="scrollTo('pasal-7')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                7. Hak Cipta &amp; Pelaporan DMCA
                            </button>
                            <button @click="scrollTo('pasal-8')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                8. Batasan Tanggung Jawab
                            </button>
                            <button @click="scrollTo('pasal-9')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                9. Hukum &amp; Yurisdiksi Sengketa
                            </button>
                            <button @click="scrollTo('pasal-10')" class="w-full text-left px-2.5 py-2 rounded-none font-mono font-medium transition-colors text-muted hover:text-electric hover:bg-cream block">
                                10. Amandemen &amp; Komunikasi
                            </button>
                        </nav>
                    </div>

                    <!-- Compliance Contact Card -->
                    <div class="bg-white rounded-none border border-ink p-4 text-xs space-y-2.5">
                        <div class="font-bold text-ink flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span>Kontak Bagian Hukum &amp; AUP</span>
                        </div>
                        <p class="text-muted leading-relaxed">
                            Laporan pelanggaran siber, abuse, atau sengketa hak cipta dapat diajukan ke:
                        </p>
                        <div class="space-y-1 font-mono text-[11px]">
                            <p class="text-ink bg-cream p-1.5 rounded-none border border-hairline">{{ \App\Support\MailIdentity::contactEmail() }}</p>
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
                            Definisi dan Ruang Lingkup Layanan
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            Perjanjian Ketentuan Layanan ini ("Perjanjian") mengatur seluruh ketentuan antara <span class="font-normal">CravaCloud Indonesia</span> ("Penyedia", "CravaCloud", "Kami") dan pihak perseorangan, perusahaan, atau organisasi ("Pelanggan", "Pengguna", "Anda") yang melakukan registrasi, pemesanan, atau menggunakan infrastruktur teknologi yang disediakan oleh CravaCloud.
                        </p>
                        <p>
                            Dalam Perjanjian ini, istilah-istilah di bawah ini memiliki definisi teknis sebagai berikut:
                        </p>
                        <ul class="space-y-2.5 pl-4 border-l-2 border-hairline text-xs">
                            <li>
                                <strong class="text-ink">Cloud VPS Starter &amp; Enterprise Series:</strong> Unit komputasi terisolasi berbasis hypervisor Kernel-based Virtual Machine (KVM) dengan vCPU, RAM murni dedikasi, media penyimpanan CBS NVMe SSD, dan 1x Dedicated IPv4.
                            </li>
                            <li>
                                <strong class="text-ink">1-Click Application Stacks:</strong> Otomasi instalasi lingkungan aplikasi dan basis data (Coolify, Dokploy, CloudPanel, aaPanel, MySQL 8.0, PostgreSQL 16/pgvector, Redis, n8n, Dify AI, Ollama, Hermes &amp; OpenClaw Agent) di atas instance pelanggan.
                            </li>
                            <li>
                                <strong class="text-ink">Kredensial Root:</strong> Hak akses tertinggi pada sistem operasi tamu (guest OS) melalui protokol Secure Shell (SSH) port 22.
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Pasal 2 -->
                <section id="pasal-2" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 2
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Registrasi Akun &amp; Keamanan Kredensial Root
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            2.1. Pelanggan wajib memberikan informasi identitas yang akurat, valid, dan dapat dipertanggungjawabkan saat melakukan registrasi melalui website resmi atau saluran otentikasi Google OAuth.
                        </p>
                        <p>
                            2.2. Pelanggan bertanggung jawab mutlak dalam menjaga kerahasiaan kata sandi akun, token sesi login, private SSH key, dan root password yang diterbitkan oleh sistem saat provisioning selesai.
                        </p>
                        <p>
                            2.3. Segala instruksi, perintah komputasi, dan perubahan konfigurasi yang dieksekusi menggunakan kredensial root server Pelanggan dianggap sah sebagai tindakan Pelanggan sendiri. CravaCloud sangat menyarankan penerapan autentikasi dua faktor (2FA TOTP), penonaktifan password login SSH, dan penggunaan public key authentication.
                        </p>
                    </div>
                </section>

                <!-- Pasal 3 -->
                <section id="pasal-3" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 3
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Batasan Tanggung Jawab Operasional &amp; Zero-Access Policy
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            3.1. Layanan CravaCloud merupakan layanan <strong>Unmanaged Cloud VPS</strong> yang berjalan di atas infrastruktur penyedia pihak ketiga (wilayah IDC DCI Indonesia Jakarta &amp; mitra jaringan Upstream). Perangkat keras fisik, hypervisor, jaringan transit, dan pasokan daya datacenter dikelola oleh penyedia infrastruktur tersebut. Tanggung jawab Penyedia terbatas pada penyerahan akses server sesuai paket yang dipesan, pengelolaan tagihan, serta penanganan permintaan layanan (seperti install ulang sistem operasi dan pemulihan server yang tidak dapat diakses) melalui dashboard dan tiket dukungan.
                        </p>
                        <p>
                            3.2. Seluruh instalasi aplikasi pihak ketiga, dependensi pustaka kode, konfigurasi reverse proxy (Nginx, Traefik, Caddy), sertifikat SSL/TLS, database internal, serta keamanan firewall tamu (UFW/iptables) berada sepenuhnya di bawah kendali dan tanggung jawab Pelanggan.
                        </p>
                        <p>
                            3.3. Karena infrastruktur fisik dikelola oleh penyedia pihak ketiga, Penyedia tidak menjanjikan persentase ketersediaan (uptime) tertentu. Gangguan yang berasal dari penyedia infrastruktur akan ditindaklanjuti oleh Penyedia dan diinformasikan melalui halaman Status Layanan.
                        </p>
                        <div class="p-4 rounded-none bg-cream border border-hairline text-xs text-ink">
                            <strong class="text-ink block mb-1 font-semibold">Kebijakan Zero-Access Server</strong>
                            CravaCloud menghormati kedaulatan data Pelanggan. Staf teknis kami tidak memiliki akses pintu belakang (backdoor) ke dalam file sistem operasi Pelanggan dan dilarang mengakses lingkungan server tanpa izin eksplisit melalui tiket dukungan teknis resmi. Password root awal yang disiapkan tim hanya digunakan untuk serah terima, dan Pelanggan disarankan segera menggantinya setelah login pertama.
                        </div>
                    </div>
                </section>

                <!-- Pasal 4 -->
                <section id="pasal-4" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 4
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Kebijakan Penggunaan yang Diizinkan (Acceptable Use Policy - AUP)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            Infrastruktur CravaCloud dilindungi oleh sistem mitigasi lalu lintas data otomatis. Pelanggan dilarang keras memanfaatkan sumber daya server untuk aktivitas yang bertentangan dengan hukum Republik Indonesia (termasuk UU ITE), regulasi internasional, serta ketertiban siber publik.
                        </p>

                        <!-- AUP Structured Table -->
                        <div class="overflow-x-auto my-4">
                            <table class="w-full text-left border-collapse border border-hairline text-xs">
                                <thead>
                                    <tr class="bg-creamDark text-ink">
                                        <th class="p-3 border border-hairline font-bold w-1/3">Kategori Pelanggaran</th>
                                        <th class="p-3 border border-hairline font-bold w-1/2">Deskripsi Tindakan Terlarang</th>
                                        <th class="p-3 border border-hairline font-bold">Tindakan Disipliner</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline">
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Serangan Siber &amp; DDoS</td>
                                        <td class="p-3 border border-hairline">Menjalankan Distributed Denial of Service, botnet C2, amplification attack, atau spoofing IP.</td>
                                        <td class="p-3 border border-hairline font-mono text-rose-700 font-semibold">Terminasi Instan</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Spamming &amp; Email Phishing</td>
                                        <td class="p-3 border border-hairline">Mengirimkan surat elektronik massal tanpa izin, pemalsuan header email, atau hosting halaman phishing bank.</td>
                                        <td class="p-3 border border-hairline font-mono text-rose-700 font-semibold">Suspensi 1 Jam</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Penambangan Kripto (Mining)</td>
                                        <td class="p-3 border border-hairline">Eksploitasi konsumsi CPU 100% secara persisten tanpa henti untuk algoritma proof-of-work (XMRig, dsb).</td>
                                        <td class="p-3 border border-hairline font-mono text-amber-700 font-semibold">Throttling / Suspend</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Konten Ilegal &amp; Judi Online</td>
                                        <td class="p-3 border border-hairline">Hosting platform perjudian online, materi pornografi, pornografi anak, atau penjualan zat terlarang.</td>
                                        <td class="p-3 border border-hairline font-mono text-rose-700 font-semibold">Terminasi &amp; Lapor Aparat</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Pemindaian Port Agresif</td>
                                        <td class="p-3 border border-hairline">Menjalankan masscan, zmap, atau vulnerability scanner terhadap jaringan publik tanpa otorisasi tertulis.</td>
                                        <td class="p-3 border border-hairline font-mono text-amber-700 font-semibold">Suspensi Jaringan</td>
                                    </tr>
                                    {{-- Empat larangan berikut menyelaraskan ketentuan ini dengan Acceptable
                                         Use Policy penyedia infrastruktur hulu. Pelanggaran atasnya berisiko
                                         menjatuhkan seluruh akun, bukan hanya layanan yang bersangkutan. --}}
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Layanan VPN &amp; Proxy</td>
                                        <td class="p-3 border border-hairline">Menjalankan layanan VPN, proxy, SOCKS, atau relay anonimisasi trafik, baik untuk umum maupun tertutup.</td>
                                        <td class="p-3 border border-hairline font-mono text-rose-700 font-semibold">Terminasi Instan</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Scraping &amp; Crawling</td>
                                        <td class="p-3 border border-hairline">Mengoperasikan layanan pengambilan data massal atau perayapan situs pihak ketiga tanpa izin pemiliknya.</td>
                                        <td class="p-3 border border-hairline font-mono text-rose-700 font-semibold">Terminasi Instan</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Agregator Torrent</td>
                                        <td class="p-3 border border-hairline">Menjalankan tracker, seedbox, atau agregator torrent, termasuk distribusi berkas berhak cipta.</td>
                                        <td class="p-3 border border-hairline font-mono text-rose-700 font-semibold">Terminasi Instan</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-hairline font-semibold text-ink">Bot &amp; Skrip Otomatis</td>
                                        <td class="p-3 border border-hairline">Menjalankan bot atau skrip otomatis yang membebani jaringan atau menyalahi ketentuan layanan pihak ketiga.</td>
                                        <td class="p-3 border border-hairline font-mono text-amber-700 font-semibold">Suspensi / Terminasi</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-4 rounded-none bg-rose-50 border border-rose-200 text-xs text-rose-800 leading-relaxed">
                            <strong class="block mb-1 font-bold">Ketentuan Nol Toleransi (Zero Tolerance Enforcement)</strong>
                            Pelanggaran berat terhadap Pasal 4 akan membatalkan hak jaminan uang kembali (no refund), mengakibatkan pemutusan instan seluruh instance terkait, dan data log trafik dapat diserahkan kepada pihak penegak hukum yang berwenang sesuai permintaan resmi.
                        </div>

                        {{-- Tiga klausul perlindungan. Tanpa pembatasan tanggung jawab, CravaCloud
                             menanggung kerugian Pelanggan tanpa batas sementara ganti rugi dari
                             penyedia infrastruktur hulu dibatasi. --}}
                        <div class="mt-4 p-4 rounded-none bg-cream border border-hairline text-xs text-ink leading-relaxed space-y-3">
                            <div>
                                <strong class="block mb-1 font-bold text-ink">4.1 Dampak Pelanggaran terhadap Infrastruktur Hulu</strong>
                                Layanan CravaCloud berjalan di atas infrastruktur penyedia pihak ketiga yang memiliki Ketentuan Penggunaan tersendiri. Pelanggaran oleh satu Pelanggan dapat mengakibatkan pembatasan atau pemutusan pada tingkat akun penyedia hulu, sehingga berdampak pada Pelanggan lain. CravaCloud berhak melakukan suspensi atau terminasi segera, tanpa pemberitahuan sebelumnya, terhadap layanan yang berpotensi menimbulkan dampak tersebut.
                            </div>
                            <div>
                                <strong class="block mb-1 font-bold text-ink">4.2 Tanggung Jawab Pencadangan Data</strong>
                                Pencadangan data sepenuhnya merupakan tanggung jawab Pelanggan. CravaCloud tidak menjamin ketersediaan salinan cadangan dan tidak bertanggung jawab atas kehilangan data akibat suspensi, terminasi, keterlambatan perpanjangan, maupun tindakan penyedia infrastruktur hulu. Data pada layanan yang telah melewati masa tenggang dihapus permanen dan tidak dapat dipulihkan.
                            </div>
                            <div>
                                <strong class="block mb-1 font-bold text-ink">4.3 Pembatasan Tanggung Jawab</strong>
                                Sepanjang diizinkan hukum yang berlaku, total tanggung jawab CravaCloud atas seluruh klaim yang timbul dari Perjanjian ini dibatasi paling banyak sebesar jumlah yang telah dibayarkan Pelanggan kepada CravaCloud dalam periode dua belas (12) bulan terakhir. CravaCloud tidak bertanggung jawab atas kerugian tidak langsung, kehilangan keuntungan, kehilangan data, maupun gangguan usaha. Tidak ada pengembalian dana untuk terminasi yang disebabkan pelanggaran terhadap Pasal 4.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Pasal 5 -->
                <section id="pasal-5" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 5
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Penagihan, Pembayaran, Masa Tenggang, dan Terminasi
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            5.1. <strong>Sistem Pembayaran Prabayar (Prepaid):</strong> Seluruh paket sewa server ditagihkan di awal periode pemakaian berdasarkan siklus langganan yang dipilih (bulanan).
                        </p>
                        @php($graceVps = app(\App\Services\SettingsService::class)->int('renewal_grace_days_vps', 5))
                        @php($graceDb = app(\App\Services\SettingsService::class)->int('renewal_grace_days_database', 25))
                        @php($graceApp = app(\App\Services\SettingsService::class)->int('renewal_grace_days_app', 25))
                        <p>
                            5.2. <strong>Pengingat Perpanjangan:</strong> Pengingat perpanjangan dikirim otomatis melalui email dan WhatsApp pada 7 (tujuh), 3 (tiga), dan 1 (satu) hari sebelum tanggal jatuh tempo, serta pada hari jatuh tempo. Perpanjangan dapat dilakukan kapan saja dari menu Tagihan di dashboard; sisa masa aktif tidak hangus.
                        </p>
                        <p>
                            5.3. <strong>Suspensi Layanan:</strong> Apabila perpanjangan belum dibayar hingga tanggal jatuh tempo, layanan berstatus suspend dan dapat dinonaktifkan sewaktu-waktu sejak tanggal tersebut. Layanan dipulihkan setelah pembayaran perpanjangan dikonfirmasi selama masa tenggang masih berlaku.
                        </p>
                        <p>
                            5.4. <strong>Masa Tenggang &amp; Terminasi Permanen:</strong> CravaCloud memberikan masa tenggang (grace period) selama {{ $graceVps }} hari kalender setelah tanggal jatuh tempo untuk layanan Cloud VPS Starter &amp; Enterprise Series, serta {{ $graceDb }} hari untuk instance database khusus dan {{ $graceApp }} hari untuk stack aplikasi terkait. Jika dalam jangka waktu tersebut tidak ada pembayaran yang dikonfirmasi, layanan diterminasi dan disk virtual server dihapus secara permanen tanpa kemungkinan pemulihan data (irreversible deletion).
                        </p>
                        <p>
                            5.5. <strong>Waktu Penyerahan Layanan:</strong> Akses server diserahkan paling lambat 1&times;24 jam sejak pembayaran terkonfirmasi (umumnya lebih cepat). Masa aktif dihitung sejak pembayaran terkonfirmasi. Apabila penyerahan melewati 1&times;24 jam, masa aktif dihitung sejak server diserahkan sebagai kompensasi, tanpa perlu diajukan.
                        </p>
                    </div>
                </section>

                <!-- Pasal 6 -->
                <section id="pasal-6" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 6
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Kebijakan Pencadangan Data (Backup Policy)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            6.1. Penyimpanan server disediakan oleh penyedia infrastruktur pihak ketiga, dan CravaCloud tidak menyediakan pencadangan otomatis. Pelanggan memegang kewajiban utama untuk membuat cadangan data (off-site backup) secara berkala ke lokasi penyimpanan independen.
                        </p>
                        <p>
                            6.2. CravaCloud tidak bertanggung jawab atas kerusakan, kehilangan, atau korupsi file yang timbul akibat kesalahan operator Pelanggan, infeksi malware pada guest OS, kegagalan software database aplikasi, atau penghapusan data akibat terminasi tagihan yang terlambat.
                        </p>
                    </div>
                </section>

                <!-- Pasal 7 -->
                <section id="pasal-7" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 7
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Hak Cipta &amp; Prosedur Pelaporan DMCA
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            7.1. Pelanggan dilarang mengunggah, mendistribusikan, atau menyiarkan materi berhak cipta tanpa izin sah dari pemegang hak cipta yang bersangkutan.
                        </p>
                        <p>
                            7.2. Apabila CravaCloud menerima pemberitahuan resmi mengenai dugaan pelanggaran hak cipta (DMCA Notice) yang valid dari pemilik karya cipta atau kuasa hukumnya, Pelanggan akan diberikan waktu 24 jam untuk memberikan tanggapan balik atau menghapus konten terkait. Kegagalan merespons akan mengakibatkan penonaktifan sementara server.
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
                            Batasan Tanggung Jawab (Limitation of Liability)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            8.1. Sepanjang diizinkan oleh hukum yang berlaku, total nilai pertanggungjawaban CravaCloud Indonesia terhadap klaim ganti rugi apa pun yang timbul dari penyediaan layanan ini dibatasi maksimal sebesar jumlah biaya berlangganan yang telah dibayarkan oleh Pelanggan pada 1 (satu) bulan terakhir untuk unit server yang bersangkutan.
                        </p>
                        <p>
                            8.2. Dalam keadaan apa pun, Penyedia tidak bertanggung jawab atas kerugian tidak langsung, kerugian insidental, hilangnya potensi keuntungan bisnis (lost profit), gangguan operasional usaha pihak ketiga, atau hilangnya reputasi dagang Pelanggan.
                        </p>
                    </div>
                </section>

                <!-- Pasal 9 -->
                <section id="pasal-9" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 9
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Hukum yang Mengatur &amp; Penyelesaian Sengketa
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            9.1. Perjanjian ini ditafsirkan, diatur, dan tunduk sepenuhnya pada peraturan perundang-undangan Negara Republik Indonesia.
                        </p>
                        <p>
                            9.2. Apabila timbul perselisihan atau perbedaan penafsiran seputar pelaksanaan Perjanjian ini, para pihak sepakat untuk mengutamakan musyawarah untuk mufakat dalam waktu 30 (tiga puluh) hari kerja. Jika mufakat tidak tercapai, para pihak sepakat untuk menyelesaikan perselisihan melalui domisili hukum Pengadilan Negeri setempat.
                        </p>
                    </div>
                </section>

                <!-- Pasal 10 -->
                <section id="pasal-10" class="scroll-mt-36 bg-white rounded-none border border-hairline p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded-none font-mono text-xs font-bold bg-electric text-white border border-electric">
                            PASAL 10
                        </span>
                        <h2 class="font-display text-xl font-bold text-ink uppercase">
                            Amandemen Ketentuan &amp; Saluran Komunikasi
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-muted leading-relaxed">
                        <p>
                            10.1. CravaCloud berhak merevisi atau memperbarui ketentuan dalam Perjanjian ini sewaktu-waktu guna menyelaraskan dengan perkembangan regulasi siber atau pembaruan arsitektur sistem. Pembaruan akan dipublikasikan pada halaman ini dengan tanggal revisi terbaru.
                        </p>
                        <p>
                            10.2. Pelanggan yang terus menggunakan layanan CravaCloud setelah berlakunya perubahan dianggap menyetujui seluruh ketentuan baru tersebut tanpa syarat.
                        </p>
                    </div>

                    <!-- Sign-off block -->
                    <div class="mt-8 pt-6 border-t border-hairline flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 text-xs text-muted">
                        <div>
                            <p class="font-normal text-ink">CravaCloud Indonesia</p>
                            <p class="text-muted mt-0.5">Departemen Hukum, Tata Kelola &amp; Kepatuhan Infrastruktur Cloud</p>
                            <p class="text-[11px] text-muted mt-2">Platform created by cravacloud.</p>
                        </div>
                        <div class="font-mono text-[11px] text-muted">
                            Dokumen Resmi: {{ config('legal.terms_version') }}
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</div>
@endsection
