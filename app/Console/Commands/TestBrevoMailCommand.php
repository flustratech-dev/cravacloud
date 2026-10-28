<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\VpsProvisionedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class TestBrevoMailCommand extends Command
{
    protected $signature = 'cravacloud:test-mail {to? : Alamat email penerima}';
    protected $description = 'Kirim email uji coba menggunakan Brevo SMTP dan template enterprise CravaCloud';

    public function handle(): int
    {
        $recipient = $this->argument('to') ?: env('CRAVACLOUD_ADMIN_EMAIL', 'admin@gmail.com');

        $this->info("Menguji pengiriman email Brevo SMTP ke: {$recipient}");
        $this->info("Host: " . config('mail.mailers.smtp.host') . ":" . config('mail.mailers.smtp.port'));
        $this->info("Username: " . config('mail.mailers.smtp.username'));
        $this->info("From: " . config('mail.from.address') . " (" . config('mail.from.name') . ")");

        try {
            // Gunakan user dengan email target penerima
            $user = User::where('email', $recipient)->first();
            if (!$user) {
                $user = User::create([
                    'full_name' => 'Administrator (Test Recipient)',
                    'email' => $recipient,
                    'username' => 'test_' . substr(md5($recipient . time()), 0, 8),
                    'password' => bcrypt('12345678'),
                    'channel' => 'website',
                    'is_admin' => false,
                ]);
            }

            $spec = new VpsSpec(['name' => 'Standard Cloud VPS 4GB']);
            $instance = new VpsInstance([
                'id' => 1,
                'hostname' => 'vps-test.cravacloud.id',
                'public_ip' => '103.186.201.50',
                'ssh_port' => 22,
                'initial_root_password' => 'cv#BrevoTest2026!',
                'os' => 'Ubuntu 24.04 LTS',
                'control_panel' => 'coolify',
            ]);
            $instance->setRelation('vpsSpec', $spec);
            $instance->setRelation('customer', $user);

            $user->notify(new VpsProvisionedNotification($instance));

            $this->info("✓ BERHASIL! Email notifikasi VPS telah berhasil dikirim ke {$recipient}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("✗ GAGAL mengirim email:");
            $this->line($e->getMessage());

            if (str_contains($e->getMessage(), 'Unauthorized IP address') || str_contains($e->getMessage(), '525')) {
                $this->warn("\n[PANDUAN SOLUSI BREVO]:");
                $this->line("Akun Brevo Anda membatasi IP pengirim (Authorized IPs).");
                $this->line("1. Buka Brevo.com -> Klik Profil (kanan atas) -> Settings -> Security -> Authorized IPs.");
                $this->line("2. Klik 'Deactivate' pada 'Blocking unauthorized IP addresses' ATAU tambahkan IP Anda ke daftar.");
            }

            return self::FAILURE;
        }
    }
}
