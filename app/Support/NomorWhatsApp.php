<?php

namespace App\Support;

/**
 * Nomor WhatsApp bisnis CravaCloud dalam setiap bentuk yang dipakai halaman.
 *
 * Satu sumber (`config('cravacloud.whatsapp')`, env `CRAVACLOUD_WHATSAPP`) untuk
 * tautan wa.me, teks "CS: 0858-…", dan data terstruktur untuk mesin pencari.
 * Sebelumnya nomor yang tampil sebagai teks ditulis mati di Blade terpisah
 * dari nomor di tautannya — begitu nomornya berganti, tombol chat menuju nomor
 * baru sementara teks di sebelahnya masih menyebut nomor lama.
 */
class NomorWhatsApp
{
    /** Format internasional tanpa tanda plus, untuk wa.me: 628xxxxxxxxxx. */
    public static function internasional(): string
    {
        $angka = preg_replace('/\D+/', '', (string) config('cravacloud.whatsapp'));

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        } elseif ($angka !== '' && ! str_starts_with($angka, '62')) {
            $angka = '62'.$angka;
        }

        return $angka;
    }

    /** Format lokal: 08xxxxxxxxxx. */
    public static function lokal(): string
    {
        $angka = self::internasional();

        return str_starts_with($angka, '62') ? '0'.substr($angka, 2) : $angka;
    }

    /** Format lokal yang mudah dibaca: 0858-0874-9131. */
    public static function tampil(): string
    {
        return preg_replace('/^(\d{4})(\d{4})(\d+)$/', '$1-$2-$3', self::lokal()) ?? self::lokal();
    }

    public static function tautan(string $pesan = ''): string
    {
        $url = 'https://wa.me/'.self::internasional();

        return $pesan === '' ? $url : $url.'?text='.rawurlencode($pesan);
    }
}
