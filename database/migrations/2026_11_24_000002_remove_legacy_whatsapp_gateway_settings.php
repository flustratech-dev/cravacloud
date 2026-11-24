<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_KEYS = [
        'wa_enabled',
        'wa_url',
        'wa_key',
        'wa_session',
        'wa_timeout',
    ];

    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->whereIn('key', self::LEGACY_KEYS)
                ->delete();
        }

        Cache::forget('cravacloud.whatsapp_settings');
    }

    public function down(): void
    {
        // Konfigurasi WA Gateway kini dikelola penuh melalui .env.
    }
};
