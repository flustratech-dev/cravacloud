<?php

namespace App\Services\LinkedAccounts;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menerapkan perubahan akun yang datang dari CravaCloud WA Gateway — sisi masuk
 * akun tertaut.
 *
 * Seluruh penulisan lewat query builder, bukan model. Itu yang memutus
 * lingkaran: perubahan yang datang dari seberang tidak memicu observer, jadi
 * tidak dikirim balik ke asalnya lalu dikirim balik lagi ke sini.
 *
 * Akun yang dibuat dari sini belum punya organisasi; EnsureOrganizationContext
 * membuatkan organisasi pribadinya saat ia pertama kali masuk, sama seperti
 * pendaftar biasa.
 */
class LinkedAccountReceiver
{
    public const DIBUAT = 'dibuat';

    public const DIPERBARUI = 'diperbarui';

    public const TIDAK_BERUBAH = 'tidak_berubah';

    public const DILINDUNGI = 'dilindungi';

    /**
     * @throws LinkedAccountConflict
     */
    public function apply(array $data): string
    {
        $email = mb_strtolower(trim($data['email']));
        $emailLama = filled($data['previous_email'] ?? null) ? mb_strtolower(trim($data['previous_email'])) : null;

        return DB::transaction(function () use ($data, $email, $emailLama) {
            $user = null;

            if ($emailLama !== null && $emailLama !== $email) {
                $user = User::where('email', $emailLama)->lockForUpdate()->first();
            }

            $user ??= User::where('email', $email)->lockForUpdate()->first();

            if (! $user) {
                $this->buat($data, $email);

                return self::DIBUAT;
            }

            // Akun admin tidak pernah diubah dari luar. Rahasia penautan yang
            // bocor di WA Gateway tidak boleh berubah jadi kunci masuk panel
            // admin VPS; identitas admin kedua aplikasi disamakan lewat env.
            if ($user->is_admin) {
                return self::DILINDUNGI;
            }

            // Akun yang SUDAH ADA di sini hanya boleh diubah oleh kiriman yang
            // emailnya sudah terbukti milik pengirimnya di aplikasi asal.
            // Tanpa syarat ini, siapa pun bisa mendaftar di WA Gateway memakai
            // email pelanggan VPS — pendaftaran di sana tidak memverifikasi
            // email — lalu penautan mengganti kata sandi akun asli pemiliknya di
            // sini. Akun baru tetap dibuat: belum ada pemilik yang bisa dirugikan.
            if (blank($data['email_verified_at'] ?? null)) {
                return self::DILINDUNGI;
            }

            $ubah = [];

            if ($user->email !== $email) {
                if (User::where('email', $email)->whereKeyNot($user->id)->exists()) {
                    throw new LinkedAccountConflict("Email {$email} sudah dipakai akun lain di CravaCloud.");
                }

                $ubah['email'] = $email;
            }

            if ($data['mode'] === LinkedAccountSync::MODE_SYNC
                && ! hash_equals((string) $user->getRawOriginal('password'), $data['password_hash'])) {
                $ubah['password'] = $data['password_hash'];
                $ubah['password_changed_at'] = now();
            }

            if ($user->email_verified_at === null && filled($data['email_verified_at'] ?? null)) {
                $ubah['email_verified_at'] = Carbon::parse($data['email_verified_at']);
            }

            if ($ubah === []) {
                return self::TIDAK_BERUBAH;
            }

            DB::table('users')->where('id', $user->id)->update($ubah + ['updated_at' => now()]);

            return self::DIPERBARUI;
        });
    }

    private function buat(array $data, string $email): void
    {
        $nama = trim((string) ($data['name'] ?? '')) ?: Str::before($email, '@');
        $nomor = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));

        DB::table('users')->insert([
            'username' => $this->usernameUnik($email),
            'full_name' => Str::limit($nama, 255, ''),
            'email' => $email,
            // Kolom nomor di sini 20 karakter; nomor yang lebih panjang dari itu
            // bukan nomor Indonesia yang sah, lebih baik kosong daripada terpotong.
            'phone' => $nomor !== '' && strlen($nomor) <= 20 ? $nomor : null,
            'password' => $data['password_hash'],
            'password_changed_at' => now(),
            'email_verified_at' => filled($data['email_verified_at'] ?? null) ? Carbon::parse($data['email_verified_at']) : null,
            'channel' => 'website',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Username wajib dan unik di sini, sementara WA Gateway tidak punya konsep
     * itu. Dibentuk dari bagian depan email supaya mudah dikenali pemiliknya,
     * dengan akhiran acak kalau sudah terpakai.
     */
    private function usernameUnik(string $email): string
    {
        $dasar = Str::of(Str::before($email, '@'))->lower()->replaceMatches('/[^a-z0-9_-]/', '')->limit(40, '')->toString();

        if (strlen($dasar) < 3) {
            $dasar = 'pengguna'.$dasar;
        }

        $calon = $dasar;

        while (User::where('username', $calon)->exists()) {
            $calon = $dasar.'-'.random_int(1000, 9999);
        }

        return $calon;
    }
}
