<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ClearTransactionsCommand extends Command
{
    protected $signature = 'transactions:clear {--force : Jalankan pembersihan langsung tanpa konfirmasi interaktif}';

    protected $description = 'Bersihkan seluruh data transaksi uji coba (orders, invoices, payments, VPS dummy) dan reset nomor urut ID.';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Apakah Anda yakin ingin menghapus seluruh data transaksi, order, invoice, dan log uji coba? Tindakan ini tidak dapat dibatalkan.')) {
            $this->warn('Pembersihan data transaksi dibatalkan.');
            return 0;
        }

        $tablesToClean = [
            'payment_transactions',
            'invoice_items',
            'invoices',
            'refunds',
            'credit_transactions',
            'renewal_reminders',
            'order_status_history',
            'fulfillment_checklists',
            'provisioning_tasks',
            'orders',
            'webhook_events',
            'vps_status_history',
            'vps_activity_logs',
            'vps_instances',
            'notifications',
            'admin_audit_logs',
        ];

        $this->info('Membuat cadangan (backup) data transaksi...');
        $backupData = [];
        $totalRecords = 0;

        foreach ($tablesToClean as $table) {
            if (Schema::hasTable($table)) {
                $rows = DB::table($table)->get();
                $count = $rows->count();
                $backupData[$table] = $rows;
                $totalRecords += $count;
                if ($count > 0) {
                    $this->line(" - Disalin: {$count} data dari tabel <comment>{$table}</comment>");
                }
            }
        }

        $backupPath = storage_path('app/backup_transactions_' . date('Ymd_His') . '.json');
        File::ensureDirectoryExists(dirname($backupPath));
        File::put($backupPath, json_encode($backupData, JSON_PRETTY_PRINT));
        $this->info("Backup tersimpan aman di: {$backupPath}");

        $this->info('Mengosongkan tabel transaksi dan me-reset ID counter...');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        foreach ($tablesToClean as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        Artisan::call('cache:clear');

        $this->newLine();
        $this->info('✓ Seluruh data transaksi uji coba berhasil dibersihkan.');
        $this->info('✓ Nomor urut ID (Auto-Increment) telah di-reset ke 1.');
        $this->info('✓ Cache aplikasi telah dibersihkan.');
        $this->info('✓ Akun pengguna (users), paket VPS, dan konfigurasi sistem tetap utuh dan aman.');

        return 0;
    }
}
