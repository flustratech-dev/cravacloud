<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Ringkasan & pembacaan konfigurasi WA Gateway yang 100% bersumber dari .env.
 *
 * Seluruh pengaturan WA Gateway tidak lagi dibaca atau ditimpa oleh tabel settings
 * di basis data agar dev, staging, dan production identik dan terkelola terpusat
 * melalui environment variable (WA_GATEWAY_*).
 */
class WhatsAppSettings
{
    private const CACHE_KEY = 'cravacloud.whatsapp_settings';

    /**
     * Kolom tampilan -> kunci config('whatsapp.*').
     */
    public const FIELDS = [
        'wa_enabled'         => ['config' => 'whatsapp.enabled',         'secret' => false, 'boolean' => true,  'integer' => false],
        'wa_url'             => ['config' => 'whatsapp.url',             'secret' => false, 'boolean' => false, 'integer' => false],
        'wa_key'             => ['config' => 'whatsapp.key',             'secret' => true,  'boolean' => false, 'integer' => false],
        'wa_session'         => ['config' => 'whatsapp.session',         'secret' => false, 'boolean' => false, 'integer' => false],
        'wa_timeout'         => ['config' => 'whatsapp.timeout',         'secret' => false, 'boolean' => false, 'integer' => true],
        'wa_connect_timeout' => ['config' => 'whatsapp.connect_timeout', 'secret' => false, 'boolean' => false, 'integer' => true],
    ];

    /**
     * Dipertahankan sebagai no-op demi kompatibilitas pemanggil lama.
     * Konfigurasi kini selalu langsung mengikuti .env / config('whatsapp.*').
     */
    public static function apply(): void
    {
        // Env-only: tidak pernah menimpa config('whatsapp.*') dari database.
    }

    /**
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function save(array $values): void
    {
        self::flush();
    }

    public static function forget(string $field): void
    {
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Ringkasan konfigurasi aktif dari .env untuk antarmuka admin (read-only).
     *
     * @return array<string, array{value: string, masked: string, from_database: bool, source: string, secret: bool}>
     */
    public static function summary(): array
    {
        $summary = [];

        foreach (self::FIELDS as $field => $meta) {
            $current = config($meta['config']);

            if ($meta['boolean']) {
                $current = $current ? '1' : '0';
            }

            $current = (string) ($current ?? '');

            $summary[$field] = [
                'value'         => $meta['secret'] ? '' : $current,
                'masked'        => $meta['secret'] ? self::mask($current) : $current,
                'from_database' => false,
                'source'        => 'env',
                'secret'        => $meta['secret'],
            ];
        }

        return $summary;
    }

    private static function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) <= 8
            ? str_repeat('•', strlen($value))
            : substr($value, 0, 4) . str_repeat('•', 8) . substr($value, -4);
    }
}
