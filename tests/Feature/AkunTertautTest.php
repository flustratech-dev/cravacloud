<?php

namespace Tests\Feature;

use App\Jobs\PushLinkedAccountJob;
use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanKeluar;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Akun tertaut dengan CravaCloud WA Gateway: satu akun, dua aplikasi, hanya
 * autentikasi. Salinan cermin dari AkunTertautTest di repo cravacloud-wa —
 * protokolnya harus sama persis di kedua sisi.
 */
class AkunTertautTest extends TestCase
{
    use RefreshDatabase;

    private const RAHASIA = 'rahasia-penautan-uji';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.linked_accounts.url' => 'https://wa.test',
            'services.linked_accounts.secret' => self::RAHASIA,
        ]);
    }

    private function kirimMasuk(array $isi, ?string $rahasia = self::RAHASIA, ?int $waktu = null)
    {
        $body = json_encode($isi);
        $waktu = (string) ($waktu ?? now()->getTimestamp());

        return $this->call('POST', '/api/internal/akun-tertaut', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AKUN_TIMESTAMP' => $waktu,
            'HTTP_X_AKUN_SIGNATURE' => hash_hmac('sha256', $waktu.'.'.$body, (string) $rahasia),
        ], $body);
    }

    private function isi(array $timpa = []): array
    {
        return array_merge([
            'mode' => 'sync',
            'origin' => 'wa',
            'email' => 'budi@contoh.id',
            'previous_email' => null,
            'name' => 'Budi Santoso',
            'phone' => '6281234567890',
            'password_hash' => Hash::make('sandi-rahasia-1'),
            // Terverifikasi di asal: hanya kiriman seperti ini yang boleh mengubah
            // akun yang sudah ada. Kasus belum terverifikasi diuji tersendiri.
            'email_verified_at' => '2026-09-19T10:00:00+07:00',
        ], $timpa);
    }

    private function pengguna(string $email, array $timpa = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Pengguna Uji',
            'username' => 'u'.substr(md5($email), 0, 10),
            'email' => $email,
            'password' => Hash::make('sandi-awal'),
        ], $timpa));
    }

    // ---------------------------------------------------------------- masuk

    public function test_endpoint_mati_total_kalau_rahasia_kosong(): void
    {
        config(['services.linked_accounts.secret' => null]);

        $this->kirimMasuk($this->isi(), 'apa-saja')->assertStatus(503);
        $this->assertSame(0, User::count());
    }

    public function test_tanda_tangan_salah_atau_kedaluwarsa_ditolak(): void
    {
        $this->kirimMasuk($this->isi(), 'rahasia-salah')->assertStatus(401);
        $this->kirimMasuk($this->isi(), self::RAHASIA, now()->subMinutes(10)->getTimestamp())->assertStatus(401);

        $this->assertSame(0, User::count());
    }

    public function test_akun_baru_dibuat_dengan_username_dan_kata_sandi_yang_sama(): void
    {
        $this->kirimMasuk($this->isi())->assertOk()->assertJson(['action' => 'dibuat']);

        $user = User::where('email', 'budi@contoh.id')->firstOrFail();
        $this->assertSame('Budi Santoso', $user->full_name);
        $this->assertSame('budi', $user->username);
        $this->assertSame('6281234567890', $user->phone);
        $this->assertTrue(Hash::check('sandi-rahasia-1', $user->password));
    }

    public function test_username_yang_sudah_terpakai_diberi_akhiran(): void
    {
        $this->pengguna('lain@contoh.id', ['username' => 'budi']);

        $this->kirimMasuk($this->isi())->assertOk();

        $this->assertMatchesRegularExpression('/^budi-\d{4}$/', User::where('email', 'budi@contoh.id')->value('username'));
    }

    public function test_perubahan_kata_sandi_diterapkan_dan_mode_link_tidak_menimpa(): void
    {
        $this->kirimMasuk($this->isi())->assertOk();

        $this->kirimMasuk($this->isi(['mode' => 'link', 'password_hash' => Hash::make('sandi-lain')]))
            ->assertOk()->assertJson(['action' => 'tidak_berubah']);
        $this->assertTrue(Hash::check('sandi-rahasia-1', User::first()->password));

        $this->kirimMasuk($this->isi(['password_hash' => Hash::make('sandi-baru-2')]))
            ->assertOk()->assertJson(['action' => 'diperbarui']);
        $this->assertTrue(Hash::check('sandi-baru-2', User::first()->password));
    }

    public function test_ganti_email_mengikuti_email_lama(): void
    {
        $this->kirimMasuk($this->isi())->assertOk();

        $this->kirimMasuk($this->isi(['email' => 'budi.baru@contoh.id', 'previous_email' => 'budi@contoh.id']))
            ->assertOk()->assertJson(['action' => 'diperbarui']);

        $this->assertSame(1, User::count(), 'Ganti email tidak boleh melahirkan akun kedua.');
        $this->assertSame('budi.baru@contoh.id', User::first()->email);
    }

    public function test_email_baru_yang_sudah_dipakai_orang_lain_ditolak(): void
    {
        $this->kirimMasuk($this->isi())->assertOk();
        $this->kirimMasuk($this->isi(['email' => 'ani@contoh.id']))->assertOk();

        $this->kirimMasuk($this->isi(['email' => 'ani@contoh.id', 'previous_email' => 'budi@contoh.id']))
            ->assertStatus(409);
    }

    /**
     * Pengambilalihan akun: seseorang mendaftar di WA Gateway memakai email
     * pelanggan VPS (pendaftaran di sana tidak memverifikasi email), lalu
     * penautan mengganti kata sandi akun asli pemiliknya di sini.
     */
    public function test_kiriman_belum_terverifikasi_tidak_mengubah_akun_yang_sudah_ada(): void
    {
        $this->kirimMasuk($this->isi())->assertOk()->assertJson(['action' => 'dibuat']);

        $this->kirimMasuk($this->isi(['email_verified_at' => null, 'password_hash' => Hash::make('sandi-penyerang')]))
            ->assertOk()->assertJson(['action' => 'dilindungi']);

        $this->assertTrue(Hash::check('sandi-rahasia-1', User::first()->password));
    }

    public function test_kiriman_belum_terverifikasi_tetap_membuat_akun_baru(): void
    {
        $this->kirimMasuk($this->isi(['email_verified_at' => null]))->assertOk()->assertJson(['action' => 'dibuat']);

        $this->assertNull(User::first()->email_verified_at);
    }

    private function cari(string $email, string $rahasia = self::RAHASIA)
    {
        $body = json_encode(['email' => $email]);
        $waktu = (string) now()->getTimestamp();

        return $this->call('POST', '/api/internal/akun-tertaut/cari', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AKUN_TIMESTAMP' => $waktu,
            'HTTP_X_AKUN_SIGNATURE' => hash_hmac('sha256', $waktu.'.'.$body, $rahasia),
        ], $body);
    }

    public function test_cari_memberikan_hash_akun_untuk_dicocokkan_di_wa(): void
    {
        $user = $this->pengguna('gita@contoh.id', ['full_name' => 'Gita', 'password' => Hash::make('sandi-gita')]);

        $jawaban = $this->cari('gita@contoh.id')->assertOk()->json('data');

        $this->assertSame('gita@contoh.id', $jawaban['email']);
        $this->assertSame('Gita', $jawaban['name']);
        $this->assertNull($jawaban['previous_email']);
        $this->assertTrue(Hash::check('sandi-gita', $jawaban['password_hash']));
        $this->assertSame($user->fresh()->getRawOriginal('password'), $jawaban['password_hash']);
    }

    public function test_cari_email_tidak_ada_404_dan_admin_tidak_diberikan(): void
    {
        $this->cari('siapa@contoh.id')->assertStatus(404);

        $admin = User::withoutEvents(fn () => $this->pengguna('admin@contoh.id'));
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $this->cari('admin@contoh.id')->assertStatus(403);
    }

    public function test_cari_tanpa_tanda_tangan_yang_benar_ditolak(): void
    {
        $this->pengguna('gita@contoh.id');

        $this->cari('gita@contoh.id', 'rahasia-salah')->assertStatus(401);
    }

    public function test_admin_tidak_bisa_diubah_dari_seberang(): void
    {
        $admin = User::withoutEvents(fn () => $this->pengguna('budi@contoh.id'));
        $admin->forceFill(['is_admin' => true])->saveQuietly();

        $this->kirimMasuk($this->isi())->assertOk()->assertJson(['action' => 'dilindungi']);

        $this->assertTrue(Hash::check('sandi-awal', $admin->fresh()->password));
    }

    public function test_kata_sandi_polos_ditolak(): void
    {
        $this->kirimMasuk($this->isi(['password_hash' => 'sandi-polos']))->assertStatus(422);

        $this->assertSame(0, User::count());
    }

    public function test_perubahan_dari_seberang_tidak_dikirim_balik(): void
    {
        Bus::fake();

        $this->kirimMasuk($this->isi())->assertOk();
        $this->kirimMasuk($this->isi(['password_hash' => Hash::make('sandi-baru-2')]))->assertOk();

        Bus::assertNothingDispatched();
        $this->assertNull(User::first()->linked_sync_pending_at);
    }

    // ---------------------------------------------------------------- keluar

    public function test_akun_baru_ditandai_dan_dikirim_setelah_respons(): void
    {
        Bus::fake();

        $user = $this->pengguna('citra@contoh.id');

        $this->assertNotNull($user->fresh()->linked_sync_pending_at);
        Bus::assertDispatchedAfterResponse(PushLinkedAccountJob::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_penautan_mati_tetap_menandai_tapi_tidak_mengirim(): void
    {
        config(['services.linked_accounts.url' => '']);
        Bus::fake();

        $user = $this->pengguna('dodi@contoh.id');

        $this->assertNotNull($user->fresh()->linked_sync_pending_at);
        Bus::assertNothingDispatched();
    }

    public function test_pengiriman_bertanda_tangan_membawa_hash_dan_melepas_penanda(): void
    {
        Bus::fake();
        Http::fake(['wa.test/*' => Http::response(['success' => true, 'action' => 'dibuat'])]);

        $user = $this->pengguna('eka@contoh.id', ['full_name' => 'Eka Putri', 'password' => Hash::make('sandi-eka')]);

        $hasil = app(LinkedAccountSync::class)->push($user);

        $this->assertSame('terkirim', $hasil['status']);
        $this->assertNull($user->fresh()->linked_sync_pending_at);

        Http::assertSent(function (PermintaanKeluar $r) {
            $tanda = hash_hmac('sha256', $r->header('X-Akun-Timestamp')[0].'.'.$r->body(), self::RAHASIA);

            return $r->url() === 'https://wa.test/api/internal/akun-tertaut'
                && hash_equals($tanda, $r->header('X-Akun-Signature')[0])
                && $r['email'] === 'eka@contoh.id'
                && $r['name'] === 'Eka Putri'
                && Hash::check('sandi-eka', $r['password_hash']);
        });
    }

    public function test_seberang_galat_penanda_tetap_dan_ditolak_penanda_dilepas(): void
    {
        Bus::fake();
        $user = $this->pengguna('fani@contoh.id');

        Http::fake(['*' => Http::sequence()
            ->push('rusak', 500)
            ->push(['error' => ['message' => 'Tanda tangan tidak cocok.']], 401)
            ->push(['error' => ['message' => 'bentrok']], 409)]);

        $this->assertSame('gagal', app(LinkedAccountSync::class)->push($user)['status']);
        $this->assertNotNull($user->fresh()->linked_sync_pending_at);

        // Rahasia yang belum disamakan bukan alasan membuang perubahan yang menunggu.
        $this->assertSame('gagal', app(LinkedAccountSync::class)->push($user)['status']);
        $this->assertNotNull($user->fresh()->linked_sync_pending_at);

        $this->assertSame('ditolak', app(LinkedAccountSync::class)->push($user)['status']);
        $this->assertNull($user->fresh()->linked_sync_pending_at);
    }

    public function test_ganti_email_ditandai_beserta_email_lama_dan_profil_biasa_tidak(): void
    {
        Bus::fake();
        $user = $this->pengguna('hadi@contoh.id');
        DB::table('users')->where('id', $user->id)->update(['linked_sync_pending_at' => null]);

        $user->refresh()->forceFill(['email' => 'hadi.baru@contoh.id'])->save();

        $this->assertNotNull($user->fresh()->linked_sync_pending_at);
        $this->assertSame('hadi@contoh.id', $user->fresh()->linked_sync_previous_email);

        DB::table('users')->where('id', $user->id)->update(['linked_sync_pending_at' => null]);
        $user->refresh()->update(['full_name' => 'Hadi Baru']);
        $this->assertNull($user->fresh()->linked_sync_pending_at);
    }

    public function test_perintah_kirim_mengulang_yang_tertunda(): void
    {
        Bus::fake();
        Http::fake(['*' => Http::response(['success' => true, 'action' => 'diperbarui'])]);
        $this->pengguna('indah@contoh.id');

        $this->artisan('akun-tertaut:kirim')->assertSuccessful();

        $this->assertSame(0, User::whereNotNull('linked_sync_pending_at')->count());
        Http::assertSentCount(1);
    }
}
