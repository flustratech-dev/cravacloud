<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti nomor WhatsApp resmi CravaCloud lama menjadi nomor baru (6285776361743)
 * secara idempoten pada tabel users (admin) dan settings.
 */
return new class extends Migration
{
    private const NEW_PHONE = '6285776361743';

    private const OLD_VARIANTS = [
        '+62 858-0874-9131',
        '+62 857-7441-0978',
        '+6285808749131',
        '+6285774410978',
        '0858-0874-9131',
        '0857-7441-0978',
        '6285808749131',
        '6285774410978',
        '085808749131',
        '085774410978',
    ];

    private const OLD_DIGITS = [
        '6285808749131',
        '085808749131',
        '6285774410978',
        '085774410978',
    ];

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('users')) {
            $admins = DB::table('users')
                ->where('is_admin', true)
                ->get(['id', 'phone']);

            foreach ($admins as $admin) {
                $digits = preg_replace('/\D+/', '', (string) $admin->phone);

                if (in_array($digits, self::OLD_DIGITS, true)) {
                    DB::table('users')
                        ->where('id', $admin->id)
                        ->update([
                            'phone' => self::NEW_PHONE,
                            'updated_at' => $now,
                        ]);
                }
            }
        }

        if (Schema::hasTable('settings')) {
            $rows = DB::table('settings')
                ->whereIn('key', ['support_whatsapp', 'company_phone', 'admin_notification_whatsapp'])
                ->get(['key', 'value']);

            foreach ($rows as $row) {
                $original = (string) ($row->value ?? '');

                if (trim($original) === '') {
                    continue;
                }

                $updated = str_ireplace(self::OLD_VARIANTS, self::NEW_PHONE, $original);

                if ($updated !== $original) {
                    DB::table('settings')
                        ->where('key', $row->key)
                        ->update([
                            'value' => $updated,
                            'updated_at' => $now,
                        ]);
                }
            }
        }

        Cache::forget(SettingsService::CACHE_KEY);
    }

    public function down(): void {}
};
