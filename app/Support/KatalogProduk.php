<?php

namespace App\Support;

use App\Models\VpsSpec;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Halaman produk publik.
 *
 * `/vps`, `/ai`, dan `/database` dulu hanya redirect ke anchor di beranda
 * (`/#pricing` dan seterusnya). Bagi pengunjung itu berfungsi; bagi mesin
 * pencari tidak ada halaman di sana sama sekali — URL yang menjawab 301 tidak
 * pernah diindeks sebagai halaman tersendiri. Akibatnya seluruh situs hanya
 * punya segelintir alamat yang bisa dirangking, dan pencarian seperti "managed
 * database murah indonesia" tidak punya halaman untuk ditunjuk.
 *
 * Tabel paket di sini sengaja disajikan berbeda dari kartu di beranda: beranda
 * untuk meyakinkan, halaman ini untuk membandingkan. Isi utamanya pun berbeda —
 * penjelasan, cara memilih, dan tanya jawab yang tidak ada di beranda — supaya
 * keduanya bukan salinan satu sama lain.
 */
class KatalogProduk
{
    /**
     * @return array<string, array{kategori: string, judul: string, h1: string, deskripsi: string, intro: string, sorotan: array<int, array{judul: string, isi: string}>, tanya: array<int, array{tanya: string, jawab: string}>, docs: array<int, string>}>
     */
    public static function semua(): array
    {
        return [
            'vps' => [
                'kategori' => 'vps',
                'judul' => 'Cloud VPS NVMe KVM Indonesia — CravaCloud',
                'h1' => 'Cloud VPS NVMe KVM Indonesia',
                'deskripsi' => 'Daftar paket Cloud VPS KVM CravaCloud dengan penyimpanan NVMe dan akses root penuh, '
                    .'datacenter Jakarta & Singapore. Bandingkan vCPU, RAM, disk, dan harganya.',
                'intro' => 'Setiap VPS CravaCloud berjalan di atas KVM — virtualisasi tingkat perangkat keras, '
                    .'bukan container. Artinya Anda memegang kernel sendiri: bebas memasang modul sistem, '
                    .'mengatur swap, dan menjalankan Docker tanpa batasan yang biasa menempel pada VPS berbasis container.',
                'sorotan' => [
                    [
                        'judul' => 'Akses root penuh sejak menit pertama',
                        'isi' => 'Kredensial root dan port SSH tampil di dashboard begitu server diserahterimakan. '
                            .'Tidak ada lapisan panel yang membatasi apa yang boleh Anda pasang.',
                    ],
                    [
                        'judul' => 'Penyimpanan NVMe, bukan SATA SSD',
                        'isi' => 'Seluruh paket memakai penyimpanan NVMe. Bedanya paling terasa pada beban yang '
                            .'banyak membaca-menulis berkas kecil: basis data, build, dan log aplikasi.',
                    ],
                    [
                        'judul' => 'Dua penyedia, dua lokasi',
                        'isi' => 'Infrastruktur berjalan di Tencent Cloud dan Lintasarta Cloudeka, dengan pilihan '
                            .'datacenter Jakarta atau Singapore. Lokasi dipilih saat memesan.',
                    ],
                    [
                        'judul' => 'Panel dipasangkan, bukan dibiarkan',
                        'isi' => 'Coolify, Dokploy, aaPanel, atau Docker polos dipasang tim sebelum server '
                            .'diserahkan, dan alamat panelnya dicantumkan di dashboard.',
                    ],
                ],
                'tanya' => [
                    [
                        'tanya' => 'Berapa lama server aktif setelah saya bayar?',
                        'jawab' => 'Server disiapkan tim setelah pembayaran terkonfirmasi — bukan otomatis dalam '
                            .'hitungan detik. Anda menerima email dan melihat status berubah menjadi Aktif di '
                            .'dashboard begitu IP publik, port SSH, dan password root siap.',
                    ],
                    [
                        'tanya' => 'Apakah saya bisa ganti sistem operasi setelah aktif?',
                        'jawab' => 'Bisa, lewat pengajuan Reinstall OS di dashboard. Perlu diingat reinstall '
                            .'menghapus seluruh isi server, jadi cadangkan dulu data yang masih diperlukan.',
                    ],
                    [
                        'tanya' => 'Apa yang terjadi kalau tagihan lewat jatuh tempo?',
                        'jawab' => 'Ada masa tenggang sebelum layanan dihentikan. Rincian tahapannya ada di '
                            .'dokumentasi Operasional Server.',
                    ],
                    [
                        'tanya' => 'Apakah VPS ini cocok untuk bot dan aplikasi yang berjalan terus-menerus?',
                        'jawab' => 'Ya. Karena KVM memberi kernel mandiri, proses latar, systemd unit, dan '
                            .'container berjalan seperti di server fisik.',
                    ],
                ],
                'docs' => ['mulai-cepat', 'keamanan', 'operasional'],
            ],

            'ai' => [
                'kategori' => 'ai_combo',
                'judul' => 'AI Combo Packages — Workstation & Coding Agent | CravaCloud',
                'h1' => 'AI Combo Packages',
                'deskripsi' => 'Paket AI Combo CravaCloud: mesin coding otomatis dan workstation bertenaga AI siap '
                    .'pakai. Bandingkan spesifikasi vCPU, RAM, penyimpanan, dan harganya.',
                'intro' => 'Mesin coding otomatis dan workstation bertenaga AI siap pakai dalam hitungan menit. '
                    .'Paket ini berjalan di atas VPS KVM yang sama dengan paket Cloud VPS, hanya dengan alokasi '
                    .'resource dan stack yang sudah disesuaikan untuk beban kerja AI.',
                'sorotan' => [
                    [
                        'judul' => 'Stack disiapkan sebelum diserahkan',
                        'isi' => 'Anda tidak memulai dari server kosong. Stack-nya dipasang tim lebih dulu, '
                            .'jadi waktu Anda habis untuk bekerja, bukan untuk memasang.',
                    ],
                    [
                        'judul' => 'Tetap server Anda sepenuhnya',
                        'isi' => 'Akses root penuh tetap berlaku. Model, runtime, dan alat lain boleh diganti '
                            .'atau ditambah sesuka Anda.',
                    ],
                    [
                        'judul' => 'Resource yang lebih lega',
                        'isi' => 'Alokasi vCPU dan RAM pada paket ini lebih besar dari paket VPS umum, karena '
                            .'beban kerja AI menahan memori lebih lama.',
                    ],
                ],
                'tanya' => [
                    [
                        'tanya' => 'Apa bedanya dengan paket Cloud VPS biasa?',
                        'jawab' => 'Perangkat kerasnya sama — KVM, NVMe, akses root. Yang berbeda ukuran '
                            .'alokasinya dan stack yang sudah terpasang saat diserahkan.',
                    ],
                    [
                        'tanya' => 'Apakah ada GPU?',
                        'jawab' => 'Paket ini berbasis CPU. Kalau kebutuhan Anda menuntut GPU, hubungi tim lebih '
                            .'dulu sebelum memesan agar tidak salah paket.',
                    ],
                    [
                        'tanya' => 'Bisakah saya menjalankan agent coding sepanjang hari?',
                        'jawab' => 'Bisa. Tidak ada pembatasan proses latar; yang membatasi hanya resource paket '
                            .'yang Anda pilih.',
                    ],
                ],
                'docs' => ['mulai-cepat', 'control-panel', 'referensi-api'],
            ],

            'database' => [
                'kategori' => 'managed_db',
                'judul' => 'Managed Database PostgreSQL & MySQL Indonesia — CravaCloud',
                'h1' => 'Managed Database',
                'deskripsi' => 'Server database terkelola CravaCloud, terpisah dari server aplikasi. Bandingkan '
                    .'paket PostgreSQL dan MySQL beserta alokasi vCPU, RAM, penyimpanan, dan harganya.',
                'intro' => 'Server database khusus yang terpisah dari server aplikasi, sehingga beban aplikasi '
                    .'tidak mengganggu database Anda. Pemisahan ini yang paling sering menyelamatkan situs saat '
                    .'trafik naik: proses web yang melonjak tidak lagi berebut memori dengan proses database.',
                'sorotan' => [
                    [
                        'judul' => 'Terpisah dari server aplikasi',
                        'isi' => 'Database berjalan di instance sendiri. Aplikasi yang bocor memori atau build '
                            .'yang berat tidak ikut menjatuhkan database.',
                    ],
                    [
                        'judul' => 'PostgreSQL atau MySQL',
                        'isi' => 'Mesin database dipilih saat memesan dan dipasang tim sebelum diserahkan, '
                            .'lengkap dengan kredensial koneksinya.',
                    ],
                    [
                        'judul' => 'Penyimpanan NVMe',
                        'isi' => 'Sama seperti paket lain, penyimpanannya NVMe — yang paling terasa justru di '
                            .'sini, karena database adalah beban baca-tulis berkas kecil yang paling padat.',
                    ],
                ],
                'tanya' => [
                    [
                        'tanya' => 'Apakah saya tetap mendapat akses root?',
                        'jawab' => 'Ya. "Managed" di sini berarti dipasang dan diserahkan siap pakai, bukan '
                            .'bahwa akses Anda dibatasi.',
                    ],
                    [
                        'tanya' => 'Bisakah database ini diakses dari server lain?',
                        'jawab' => 'Bisa, dan itu memang bentuk pemakaian yang dimaksud. Pastikan firewall hanya '
                            .'membuka port database untuk alamat yang memang perlu — langkahnya ada di '
                            .'dokumentasi Keamanan & Hardening.',
                    ],
                    [
                        'tanya' => 'Apakah ada cadangan otomatis?',
                        'jawab' => 'Pencadangan disiapkan sesuai kesepakatan saat pemesanan. Tanyakan lebih dulu '
                            .'kalau ini menentukan keputusan Anda.',
                    ],
                ],
                'docs' => ['keamanan', 'operasional', 'infrastruktur'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function ambil(string $slug): array
    {
        $semua = self::semua();

        if (! isset($semua[$slug])) {
            throw new NotFoundHttpException("Halaman produk '{$slug}' tidak ada.");
        }

        return $semua[$slug] + ['slug' => $slug];
    }

    /**
     * Paket aktif untuk sebuah halaman produk, termurah lebih dulu.
     *
     * Dibaca dari tabel, bukan ditulis ulang di Blade: halaman yang menjanjikan
     * angka berbeda dari yang ditagihkan adalah janji yang kita langgar.
     */
    public static function paket(string $slug): Collection
    {
        return VpsSpec::query()
            ->where('category', self::ambil($slug)['kategori'])
            ->orderBy('sell_price')
            ->get();
    }
}
