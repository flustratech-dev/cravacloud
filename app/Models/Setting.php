<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penyimpanan key-value bertipe untuk branding, profil perusahaan, dan maintenance.
 *
 * Jangan membaca model ini langsung dari controller atau view.
 * Gunakan App\Services\SettingsService (helper: setting()) agar hasilnya ter-cache.
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Nilai mentah dari kolom value di-cast sesuai kolom type.
     */
    public function getTypedValueAttribute(): mixed
    {
        return self::castValue($this->value, $this->type);
    }

    public static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true) ?: [],
            default => $value,
        };
    }

    /**
     * Ubah nilai PHP menjadi bentuk string yang aman disimpan.
     */
    public static function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
