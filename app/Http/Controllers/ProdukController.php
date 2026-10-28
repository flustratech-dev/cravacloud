<?php

namespace App\Http\Controllers;

use App\Support\KatalogProduk;
use Illuminate\Contracts\View\View;

/**
 * Halaman produk: /vps, /ai, /database.
 *
 * Ketiganya dulu hanya redirect ke anchor di beranda. Alasan mengubahnya
 * menjadi halaman sungguhan ada di App\Support\KatalogProduk.
 */
class ProdukController extends Controller
{
    public function __invoke(string $slug): View
    {
        $produk = KatalogProduk::ambil($slug);

        return view('pages.produk', [
            'produk' => $produk,
            'paket' => KatalogProduk::paket($slug),
            'remah' => [
                ['name' => 'Beranda', 'url' => url('/')],
                ['name' => $produk['h1']],
            ],
        ]);
    }
}
