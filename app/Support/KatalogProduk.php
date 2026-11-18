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
                'judul' => 'CravaCloud Katalog VPS NVMe KVM Indonesia (Starter & Enterprise)',
                'h1' => 'Cloud VPS NVMe KVM Indonesia',
                'deskripsi' => 'Daftar paket Cloud VPS KVM NVMe CravaCloud Starter & Enterprise Series (2–8 vCPU, 2–32 GB RAM) di IDC DCI Jakarta. Bandingkan spesifikasi, bandwidth, dan harganya.',
                'intro' => 'Setiap Cloud VPS CravaCloud Starter & Enterprise Series berjalan di atas virtualisasi KVM perangkat keras murni dengan RAM dedikasi tanpa overselling, storage CBS NVMe SSD hingga 26.000 IOPS di IDC DCI Indonesia Jakarta (8–15 ms), serta 1x Dedicated IPv4 dan 1-Click Application Stacks siap pakai.',
                'sorotan' => [
                    [
                        'judul' => 'Akses root penuh & 1x Dedicated IPv4',
                        'isi' => 'Kredensial root dan port SSH langsung aktif di dashboard dengan 1 alamat IPv4 statis dedikasi di setiap node tanpa sharing antar pelanggan.',
                    ],
                    [
                        'judul' => 'Storage CBS NVMe SSD hingga 26.000 IOPS',
                        'isi' => 'Seluruh paket Starter XS hingga Enterprise Extreme memakai disk CBS NVMe berkecepatan tinggi dengan RAM murni tanpa overselling.',
                    ],
                    [
                        'judul' => 'Datacenter IDC DCI Indonesia Jakarta (8–15 ms)',
                        'isi' => 'Terhubung langsung ke jaringan peering lokal IIX & OpenIXP untuk akses sangat responsif bagi pengguna dan aplikasi bisnis di Indonesia.',
                    ],
                    [
                        'judul' => 'Bebas pusing DevOps (1-Click Application Stacks)',
                        'isi' => 'Otomatis terinstal tanpa biaya tambahan: Coolify, Dokploy, CloudPanel, aaPanel, MySQL 8.0, PostgreSQL 16 (pgvector), Redis, n8n, Dify AI, Ollama, Hermes & OpenClaw Agent.',
                    ],
                ],
                'tanya' => [
                    [
                        'tanya' => 'Berapa lama server aktif setelah saya bayar?',
                        'jawab' => 'Server diproses oleh Zero-Touch Engine otomatis segera setelah pembayaran QRIS Instan atau Virtual Account terkonfirmasi (estimasi 45–75 detik). IPv4 statis, port SSH, dan kredensial root tampil langsung di Client Console.',
                    ],
                    [
                        'tanya' => 'Apakah saya bisa ganti sistem operasi atau panel setelah aktif?',
                        'jawab' => 'Bisa, langsung melalui fitur Reinstall OS dari dashboard pelanggan dalam hitungan detik/menit. Pastikan mencadangkan data penting terlebih dahulu karena reinstall memformat ulang disk.',
                    ],
                    [
                        'tanya' => 'Apa yang terjadi kalau tagihan lewat jatuh tempo?',
                        'jawab' => 'Ada masa tenggang sebelum layanan dihentikan permanen. Rincian siklus penagihan dan perpanjangan tersedia di dokumentasi Operasional Server.',
                    ],
                    [
                        'tanya' => 'Apakah VPS ini cocok untuk Docker, PaaS Coolify, atau AI Agent 24 jam?',
                        'jawab' => 'Ya. Karena KVM menyediakan isolasi kernel penuh dan RAM dedikasi tanpa overselling, container Docker, Coolify, database produksi, hingga agent n8n/Dify/Hermes berjalan stabil.',
                    ],
                ],
                'docs' => ['mulai-cepat', 'keamanan', 'operasional'],
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
        $retiredLegacyNames = [
            'Student Basic',
            'Standard',
            'Premium',
            'Startup',
            'Business',
            'Mahasiswa Basic',
        ];

        return VpsSpec::query()
            ->where('category', self::ambil($slug)['kategori'])
            ->whereNotIn('name', $retiredLegacyNames)
            ->orderBy('sell_price')
            ->get();
    }
}
