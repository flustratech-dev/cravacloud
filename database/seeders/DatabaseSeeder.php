<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VpsSpec;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed & sync VPS product specs
        $this->call(VpsSpecSeeder::class);

        // 2. Single Admin Account (Dynamically Loaded from .env for Security)
        $adminEmail = env('ADMIN_EMAIL', env('SEED_ADMIN_EMAIL', 'admin@gmail.com'));
        $adminUsername = env('ADMIN_USERNAME', env('SEED_ADMIN_USERNAME', 'admin'));
        $adminName = env('ADMIN_NAME', env('SEED_ADMIN_NAME', 'Administrator'));
        $adminPassword = env('ADMIN_PASSWORD', env('SEED_ADMIN_PASSWORD', '12345678'));
        $adminPhone = env('ADMIN_PHONE', env('SEED_ADMIN_PHONE', env('CRAVACLOUD_WHATSAPP', '6285808749131')));
        $adminCompany = env('ADMIN_COMPANY', env('SEED_ADMIN_COMPANY', 'CravaCloud Indonesia'));

        $admin = User::where('email', $adminEmail)
            ->orWhere('username', $adminUsername)
            ->first();

        if (! $admin) {
            User::create([
                'email' => $adminEmail,
                'username' => $adminUsername,
                'full_name' => $adminName,
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
                'email_verified_at' => now(),
                'channel' => 'website',
                'phone' => $adminPhone,
                'company' => $adminCompany,
                'address' => null,
            ]);
        } else {
            // Jika akun admin sudah ada, jangan reset password agar password admin yang telah diganti tidak kembali ke default 12345678
            $admin->forceFill([
                'email' => $adminEmail,
                'username' => $adminUsername,
                'full_name' => $adminName,
                'is_admin' => true,
                'channel' => 'website',
                'phone' => $adminPhone,
                'company' => $adminCompany,
            ])->save();
        }

        // 3. Seed RBAC + backfill Personal Org untuk existing users.
        $this->call(RolePermissionSeeder::class);

        // Phase 1 - pengaturan sistem, branding, dan komponen halaman status.
        $this->call(SettingsSeeder::class);

        // Phase 2 - template surel bawaan.
        $this->call(NotificationTemplateSeeder::class);

        // Phase 4 - registry payment gateway.
        $this->call(PaymentGatewaySeeder::class);
    }
}
