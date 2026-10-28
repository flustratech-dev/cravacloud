<?php

namespace App\Http\Controllers;

use App\Support\KatalogDokumentasi;
use Illuminate\Contracts\View\View;

/**
 * Dokumentasi publik.
 *
 * Dulu satu halaman berisi seluruh isi dokumentasi; sekarang satu indeks dan
 * satu halaman per kelompok topik, masing-masing dengan alamat, judul, dan
 * deskripsi sendiri. Pemecahannya dijelaskan di KatalogDokumentasi.
 */
class DocsController extends Controller
{
    public function index(): View
    {
        return view('pages.docs.index', [
            'seoJudul' => 'Dokumentasi Teknis & Panduan Arsitektur Cloud — CravaCloud',
            'seoDeskripsi' => 'Panduan lengkap CravaCloud: provisioning VPS, akses SSH, pilihan datacenter, '
                .'instalasi Coolify & Dokploy, pengamanan UFW & Fail2ban, sampai referensi API.',
            'remah' => [
                ['name' => 'Beranda', 'url' => url('/')],
                ['name' => 'Dokumentasi'],
            ],
        ]);
    }

    public function kelompok(string $slug): View
    {
        $kelompok = KatalogDokumentasi::ambil($slug);
        $urutan = array_keys(KatalogDokumentasi::kelompok());
        $posisi = array_search($slug, $urutan, true);

        return view('pages.docs.kelompok', [
            'kelompok' => $kelompok,
            'kelompokAktif' => $slug,
            'sebelumnya' => $posisi > 0 ? $urutan[$posisi - 1] : null,
            'berikutnya' => $urutan[$posisi + 1] ?? null,
            'seoJudul' => $kelompok['judul'].' — Dokumentasi CravaCloud',
            'seoDeskripsi' => $kelompok['deskripsi'],
            'remah' => [
                ['name' => 'Beranda', 'url' => url('/')],
                ['name' => 'Dokumentasi', 'url' => route('docs')],
                ['name' => $kelompok['judul']],
            ],
        ]);
    }
}
