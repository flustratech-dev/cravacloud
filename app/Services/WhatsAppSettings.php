<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial & Pengaturan WA Gateway yang dapat diubah lewat panel admin, bukan .env.
 *
 * Nilai yang tersimpan di tabel settings menimpa config('whatsapp.*') saat boot.
 * Pemanggil membaca config atau WhatsAppGateway seperti biasa.
 *
 * Kolom secret (API Key) disimpan terenkripsi di basis data dan ditampilkan
 * tersamar di form admin untuk mencegah kebocoran visual.
 */
class WhatsAppSettings
{
    private const CACHE_KEY = 'cravacloud.whatsapp_settings';

    /**
     * Kolom panel -> kunci config yang ditimpa.
     */
    public const FIELDS = [
        'wa_enabled' => ['config' => 'whatsapp.enabled', 'secret' => false, 'boolean' => true, 'integer' => false],
        'wa_url'     => ['config' => 'whatsapp.url',     'secret' => false, 'boolean' => false, 'integer' => false],
        'wa_key'     => ['config' => 'whatsapp.key',     'secret' => true,  'boolean' => false, 'integer' => false],
        'wa_session' => ['config' => 'whatsapp.session', 'secret' => false, 'boolean' => false, 'integer' => false],
        'wa_timeout' => ['config' => 'whatsapp.timeout', 'secret' => false, 'boolean' => false, 'integer' => true],
    ];

    private static ?array $memo = null;

    /**
     * Menimpa config('whatsapp.*') dengan nilai dari basis data saat boot.
     */
    public static function apply(): void
    {
        foreach (self::stored() as $field => $value) {
            if (! isset(self::FIELDS[$field])) {
                continue;
            }

            $meta = self::FIELDS[$field];

            if ($meta['boolean']) {
                $castValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif ($meta['integer']) {
                $castValue = (int) $value;
            } else {
                $castValue = $value;
            }

            config([$meta['config'] => $castValue]);
        }
    }

    /**
     * Nilai mentah yang tersimpan di basis data, sudah didekripsi.
     *
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return self::$memo = [];
            }

            return self::$memo = Cache::rememberForever(self::CACHE_KEY, self::readFromDatabase(...));
        } catch (\Throwable $e) {
            return self::$memo = [];
        }
    }

    /**
     * Menyimpan nilai form pengaturan admin ke tabel settings.
     *
     * @param array<string, mixed> $values
     */
    public static function save(array $values): void
    {
        foreach (self::FIELDS as $field => $meta) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $value = $values[$field];

            if ($meta['boolean']) {
                $setting = Setting::firstOrNew(['key' => $field]);
                $setting->value = $value ? '1' : '0';
                $setting->type = 'boolean';
                $setting->group = 'whatsapp';
                $setting->label = 'Status Pengiriman WhatsApp';
                $setting->save();
                continue;
            }

            if ($meta['integer']) {
                $valInt = (int) $value;
                $setting = Setting::firstOrNew(['key' => $field]);
                $setting->value = (string) ($valInt > 0 ? $valInt : 10);
                $setting->type = 'integer';
                $setting->group = 'whatsapp';
                $setting->label = 'Timeout WhatsApp Gateway';
                $setting->save();
                continue;
            }

            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                // Jangan hapus secret yang dikirim kosong (artinya biarkan nilai lama)
                if (! $meta['secret']) {
                    Setting::where('key', $field)->delete();
                }
                continue;
            }

            $setting = Setting::firstOrNew(['key' => $field]);
            $setting->value = $meta['secret'] ? Crypt::encryptString($value) : $value;
            $setting->type = 'string';
            $setting->group = 'whatsapp';
            $setting->label = $field;
            $setting->save();
        }

        self::flush();
    }

    /**
     * Menghapus satu pengaturan sehingga nilainya kembali mengikuti .env / config default.
     */
    public static function forget(string $field): void
    {
        if (! isset(self::FIELDS[$field])) {
            return;
        }

        Setting::where('key', $field)->delete();
        self::flush();
    }

    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Ringkasan pengaturan untuk antarmuka admin.
     *
     * @return array<string, array{value: string, masked: string, from_database: bool, secret: bool}>
     */
    public static function summary(): array
    {
        $stored = self::stored();
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
                'from_database' => array_key_exists($field, $stored),
                'secret'        => $meta['secret'],
            ];
        }

        return $summary;
    }

    private static function readFromDatabase(): array
    {
        $rows = Setting::whereIn('key', array_keys(self::FIELDS))
            ->pluck('value', 'key')
            ->toArray();

        $result = [];

        foreach ($rows as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $result[$field] = self::FIELDS[$field]['secret'] ? self::decrypt($value) : $value;
        }

        return $result;
    }

    private static function decrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
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
