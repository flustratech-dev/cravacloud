<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Peta halaman dokumentasi publik.
 *
 * Dokumentasi dulu satu halaman sepanjang 1173 baris berisi 21 seksi ber-anchor.
 * Bagi pembaca itu berarti satu gulungan panjang tanpa tempat berpijak; bagi
 * mesin pencari berarti satu URL dengan satu judul untuk dua puluh satu topik
 * berbeda — tidak ada satu pun di antaranya yang bisa dirangking sendiri, dan
 * hasil pencarian untuk "cara pasang Coolify di VPS" tidak pernah menunjuk ke
 * sini. Sekarang seksinya dikelompokkan menjadi halaman tersendiri.
 *
 * Pengelompokannya bukan karangan baru: kategori ini sudah dipakai navigasi
 * dokumentasi sejak awal. Yang berubah hanya bahwa tiap kategori kini punya
 * alamat sendiri.
 *
 * Seksi sengaja TIDAK dipecah satu-satu menjadi 21 halaman. Empat di antaranya
 * hanya selusin baris; halaman setipis itu dinilai Google sebagai isi dangkal
 * dan justru menyeret turun yang lain.
 */
class KatalogDokumentasi
{
    /** Letak partial tiap seksi, relatif terhadap resources/views. */
    private const DIR_SEKSI = 'pages/docs/seksi';

    /**
     * Kelompok dokumentasi, berurutan sesuai alur baca.
     *
     * `judul` dipakai di navigasi dan remah jejak, `deskripsi` menjadi meta
     * description halaman — karena itu masing-masing harus benar-benar berbeda:
     * deskripsi yang sama di tujuh halaman membuat cuplikan hasil pencarian
     * berhenti menjelaskan halaman yang sedang ditampilkan.
     *
     * @return array<string, array{judul: string, navigasi: string, ringkas: string, deskripsi: string, ikon: string, seksi: array<string, string>}>
     */
    public static function kelompok(): array
    {
        return [
            'mulai-cepat' => [
                'judul' => 'Mulai Cepat',
                'navigasi' => 'Mulai Cepat & Orientasi',
                'ringkas' => 'Arsitektur KVM, alur server disiapkan setelah pembayaran, dan cara masuk lewat SSH.',
                'deskripsi' => 'Arsitektur KVM CravaCloud, alur aktivasi server setelah pembayaran terkonfirmasi, dan cara mengakses VPS lewat SSH beserta pengelolaan kredensialnya.',
                'ikon' => 'M13 10V3L4 14h7v7l9-11h-7z',
                'seksi' => [
                    'intro' => 'Pengenalan Arsitektur Cloud',
                    'provisioning' => 'Alur Aktivasi Server',
                    'ssh-access' => 'Akses SSH & Kredensial',
                ],
            ],
            'infrastruktur' => [
                'judul' => 'Infrastruktur & Jaringan',
                'navigasi' => 'Infrastruktur & Jaringan',
                'ringkas' => 'Dua penyedia, dua lokasi datacenter, dan penyimpanan NVMe di baliknya.',
                'deskripsi' => 'Arsitektur dual-provider Tencent Cloud & Lintasarta Cloudeka, lokasi datacenter Jakarta dan Singapore beserta peering jaringannya, serta penyimpanan NVMe SSD.',
                'ikon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                'seksi' => [
                    'dual-provider' => 'Dual-Provider (Tencent & Cloudeka)',
                    'datacenters' => 'Datacenter Jakarta & Singapore',
                    'storage' => 'Penyimpanan NVMe SSD',
                ],
            ],
            'control-panel' => [
                'judul' => 'Control Panel & Stack',
                'navigasi' => 'Control Panel & Stacks',
                'ringkas' => 'Coolify, Dokploy, aaPanel, atau Docker polos — mana yang cocok dan cara memasangnya.',
                'deskripsi' => 'Memasang Coolify, Dokploy, aaPanel (LEMP/LAMP), atau Docker Engine standalone di VPS CravaCloud: perbandingan singkat dan langkah instalasinya.',
                'ikon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
                'seksi' => [
                    'stack-coolify' => 'Coolify Platform Deployment',
                    'stack-dokploy' => 'Dokploy Modern PaaS',
                    'stack-aapanel' => 'aaPanel & Traditional Stack (LEMP/LAMP)',
                    'stack-docker' => 'Docker Engine Standalone',
                ],
            ],
            'keamanan' => [
                'judul' => 'Keamanan & Hardening',
                'navigasi' => 'Keamanan & Hardening',
                'ringkas' => 'Firewall, kunci SSH, dan peredam brute force — tiga langkah pertama di server baru.',
                'deskripsi' => 'Mengamankan VPS CravaCloud: konfigurasi firewall UFW dan kebijakan port, hardening SSH key-only, serta Fail2ban untuk meredam serangan brute force.',
                'ikon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
                'seksi' => [
                    'security-ufw' => 'Firewall UFW & Port Policy',
                    'security-ssh' => 'Hardening SSH Key-Only',
                    'security-fail2ban' => 'Fail2ban & Brute Force',
                ],
            ],
            'operasional' => [
                'judul' => 'Operasional Server',
                'navigasi' => 'Operasional Siklus Hidup',
                'ringkas' => 'Yang perlu dilakukan saat server diam, perlu dipasang ulang, atau tagihannya jatuh tempo.',
                'deskripsi' => 'Menangani server yang tidak merespons, reinstall sistem operasi, memantau pemakaian resource, dan memahami siklus tagihan serta masa tenggang CravaCloud.',
                'ikon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                'seksi' => [
                    'ops-power' => 'Restart & Server Tidak Merespons',
                    'ops-reinstall' => 'Reinstall Sistem Operasi',
                    'ops-monitoring' => 'Memantau Pemakaian Resource',
                    'ops-billing' => 'Siklus Tagihan & Masa Tenggang',
                ],
            ],
            'referensi-api' => [
                'judul' => 'Referensi API',
                'navigasi' => 'Referensi API & Integrasi',
                'ringkas' => 'Autentikasi, endpoint yang tersedia, dan bentuk respons galatnya.',
                'deskripsi' => 'Referensi API CravaCloud: autentikasi dan security header, endpoint status instance (GET), otomasi Shopee (POST), serta standar kode error dan bentuk responsnya.',
                'ikon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4',
                'seksi' => [
                    'api-auth' => 'Autentikasi & Security Header',
                    'api-vps-status' => 'GET Status Instance',
                    'api-shopee-process' => 'POST Otomasi Shopee',
                    'api-errors' => 'Standar Kode Error API',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function ambil(string $slug): array
    {
        $kelompok = self::kelompok();

        if (! isset($kelompok[$slug])) {
            throw new NotFoundHttpException("Kelompok dokumentasi '{$slug}' tidak ada.");
        }

        return $kelompok[$slug] + ['slug' => $slug];
    }

    public static function ada(string $slug): bool
    {
        return isset(self::kelompok()[$slug]);
    }

    /**
     * Peta id seksi ke slug kelompok yang sekarang memuatnya.
     *
     * Dipakai halaman indeks untuk melempar tautan lama berbentuk
     * `/docs#stack-coolify` ke alamat barunya. Anchor tidak pernah dikirim ke
     * server, jadi tidak ada redirect yang bisa menanganinya — dan tautan lama
     * itu sudah tersebar di riwayat chat dan bookmark pelanggan.
     *
     * @return array<string, string>
     */
    public static function petaSeksi(): array
    {
        $peta = [];

        foreach (self::kelompok() as $slug => $kelompok) {
            foreach (array_keys($kelompok['seksi']) as $seksi) {
                $peta[$seksi] = $slug;
            }
        }

        return $peta;
    }

    /** Nama view partial sebuah seksi. */
    public static function viewSeksi(string $seksi): string
    {
        return str_replace('/', '.', self::DIR_SEKSI).'.'.$seksi;
    }

    /**
     * Kapan isi sebuah kelompok terakhir berubah, untuk <lastmod> di sitemap.
     *
     * Dibaca dari berkas seksinya, bukan waktu permintaan: sitemap yang menyebut
     * setiap URL "baru saja berubah" setiap kali diminta tidak dipercaya Google,
     * dan tanggalnya berhenti berarti apa pun.
     */
    public static function diubahPada(string $slug): ?string
    {
        if (! self::ada($slug)) {
            return null;
        }

        $waktu = [];

        foreach (array_keys(self::kelompok()[$slug]['seksi']) as $seksi) {
            $berkas = resource_path(self::DIR_SEKSI."/{$seksi}.blade.php");

            if (is_file($berkas)) {
                $waktu[] = filemtime($berkas);
            }
        }

        return $waktu === [] ? null : Carbon::createFromTimestamp(max($waktu))->toAtomString();
    }
}
