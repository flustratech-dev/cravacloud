<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D10: encrypted-at-rest casts + one-time idempotent re-encryption of legacy
 * plaintext credentials. Values are asserted via decryption, never printed.
 */
class CredentialReencryptTest extends TestCase
{
    use RefreshDatabase;

    private function spec(): VpsSpec
    {
        return VpsSpec::create([
            'name' => 'Starter XS', 'category' => 'vps',
            'cpu' => 2, 'ram' => 2, 'disk' => 40, 'bandwidth' => 20,
            'cost_price' => 85000, 'sell_price' => 115000, 'is_active' => true,
        ]);
    }

    private function user(): User
    {
        return User::create([
            'full_name' => 'Pelanggan Enkripsi', 'username' => 'enk' . uniqid(),
            'email' => 'enk' . uniqid() . '@test.id', 'password' => bcrypt('password123'), 'is_admin' => false,
        ]);
    }

    /** Insert a legacy order with RAW plaintext credentials (pre-D10 data). */
    private function legacyOrder(string $rootPass, ?string $dbPass): int
    {
        $user = $this->user();
        $spec = $this->spec();

        return DB::table('orders')->insertGetId([
            'customer_id' => $user->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 115000,
            'hostname' => 'cv-enk-' . uniqid(),
            'root_password' => $rootPass,
            'db_password' => $dbPass,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_new_writes_are_encrypted_at_rest(): void
    {
        $user = $this->user();
        $spec = $this->spec();

        $order = Order::create([
            'customer_id' => $user->id, 'vps_spec_id' => $spec->id,
            'control_panel' => 'none', 'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404', 'status' => 'pending', 'channel' => 'website',
            'payment_method' => 'qris', 'amount' => 115000,
            'hostname' => 'cv-new-' . uniqid(),
            'root_password' => 'KuatSekali123!',
            'db_password' => 'DbKuat456!',
        ]);

        $rawRoot = DB::table('orders')->where('id', $order->id)->value('root_password');
        $rawDb = DB::table('orders')->where('id', $order->id)->value('db_password');

        $this->assertNotSame('KuatSekali123!', $rawRoot);
        $this->assertNotSame('DbKuat456!', $rawDb);
        $this->assertSame('KuatSekali123!', Crypt::decryptString($rawRoot));
        $this->assertSame('DbKuat456!', Crypt::decryptString($rawDb));

        // Model reads transparently decrypt.
        $this->assertSame('KuatSekali123!', $order->fresh()->root_password);
        $this->assertSame('DbKuat456!', $order->fresh()->db_password);
    }

    public function test_command_converts_legacy_plaintext_and_is_idempotent(): void
    {
        $id = $this->legacyOrder('LegacyRoot789!', 'LegacyDb012!');

        $this->artisan('credentials:reencrypt')->assertSuccessful();

        $rawRoot = DB::table('orders')->where('id', $id)->value('root_password');
        $rawDb = DB::table('orders')->where('id', $id)->value('db_password');
        $this->assertSame('LegacyRoot789!', Crypt::decryptString($rawRoot));
        $this->assertSame('LegacyDb012!', Crypt::decryptString($rawDb));

        // Model reads work after conversion.
        $order = Order::find($id);
        $this->assertSame('LegacyRoot789!', $order->root_password);

        // Second run: nothing left to convert (idempotent).
        $this->artisan('credentials:reencrypt')
            ->expectsOutputToContain('0 dikonversi')
            ->assertSuccessful();
    }

    public function test_dry_run_does_not_modify_data(): void
    {
        $id = $this->legacyOrder('TetapPlaintext1!', null);

        $this->artisan('credentials:reencrypt', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('TetapPlaintext1!', DB::table('orders')->where('id', $id)->value('root_password'));
    }

    public function test_null_and_empty_values_are_skipped(): void
    {
        $id = $this->legacyOrder('', null);

        $this->artisan('credentials:reencrypt')
            ->expectsOutputToContain('1 kosong')
            ->assertSuccessful();

        $this->assertSame('', DB::table('orders')->where('id', $id)->value('root_password'));
        $this->assertNull(DB::table('orders')->where('id', $id)->value('db_password'));
    }

    public function test_vps_instance_db_password_encrypted_and_reencryptable(): void
    {
        $user = $this->user();

        // Legacy raw insert (pre-D10 instance row).
        $id = DB::table('vps_instances')->insertGetId([
            'customer_id' => $user->id,
            'hostname' => 'cv-db-' . uniqid(),
            'status' => 'running',
            'provider' => 'manual',
            'db_password' => 'LegacyInstanceDb3!',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('credentials:reencrypt')->assertSuccessful();

        $raw = DB::table('vps_instances')->where('id', $id)->value('db_password');
        $this->assertSame('LegacyInstanceDb3!', Crypt::decryptString($raw));
        $this->assertSame('LegacyInstanceDb3!', VpsInstance::find($id)->db_password);
    }

    public function test_already_encrypted_values_untouched(): void
    {
        $user = $this->user();
        $spec = $this->spec();
        $order = Order::create([
            'customer_id' => $user->id, 'vps_spec_id' => $spec->id,
            'control_panel' => 'none', 'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404', 'status' => 'pending', 'channel' => 'website',
            'payment_method' => 'qris', 'amount' => 115000,
            'hostname' => 'cv-enc-' . uniqid(),
            'root_password' => 'SudahAman999!',
        ]);
        $before = DB::table('orders')->where('id', $order->id)->value('root_password');

        $this->artisan('credentials:reencrypt')
            ->expectsOutputToContain('1 sudah terenkripsi')
            ->assertSuccessful();

        $this->assertSame($before, DB::table('orders')->where('id', $order->id)->value('root_password'));
    }
}