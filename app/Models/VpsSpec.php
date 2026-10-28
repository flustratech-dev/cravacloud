<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VpsSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'cpu',
        'ram',
        'disk',
        'bandwidth',
        'cost_price',
        'sell_price',
        'payment_url',
        'is_active',
        'is_renewable',
        'replacement_spec_id',
        'tagline',
        'target_audience',
        'features',
        'solution',
        'badge',
        'default_stack',
        'allowed_providers',
        'default_provider',
    ];

    protected $casts = [
        'features' => 'array',
        'allowed_providers' => 'array',
        'is_active' => 'boolean',
        'is_renewable' => 'boolean',
        'replacement_spec_id' => 'integer',
        'cpu' => 'integer',
        'ram' => 'integer',
        'disk' => 'integer',
        'bandwidth' => 'integer',
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
    ];

    protected $appends = [
        'allowed_providers',
        'default_provider',
        'is_ai_package',
        'is_database_package',
        'is_direct_checkout',
        'lifecycle_status',
        'lifecycle_label',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('landing.specs');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('landing.specs');
        });
    }

    public function isAiPackage(): bool
    {
        return ($this->category === 'ai_combo')
            || in_array((int)$this->id, [7, 8, 9, 10], true)
            || str_contains(strtolower($this->name), 'combo')
            || str_contains(strtolower($this->name), 'terminal coding agent')
            || str_contains(strtolower($this->name), 'cloud ai workstation')
            || str_contains(strtolower($this->name), 'hermes')
            || str_contains(strtolower($this->name), 'rag');
    }

    public function getIsAiPackageAttribute(): bool
    {
        return $this->isAiPackage();
    }

    public function isDatabasePackage(): bool
    {
        return ($this->category === 'managed_db')
            || in_array((int)$this->id, [11, 12, 13], true)
            || str_starts_with(strtolower($this->name), 'db ')
            || str_contains(strtolower($this->name), 'db enterprise');
    }

    public function getIsDatabasePackageAttribute(): bool
    {
        return $this->isDatabasePackage();
    }

    public function isDirectCheckout(): bool
    {
        return $this->isAiPackage() || $this->isDatabasePackage();
    }

    public function getIsDirectCheckoutAttribute(): bool
    {
        return $this->isDirectCheckout();
    }

    public function allowedProviders(): array
    {
        // Jika admin secara eksplisit menyimpan allowed_providers di DB, gunakan itu
        $raw = $this->attributes['allowed_providers'] ?? null;
        if (!empty($raw)) {
            $dbVal = is_string($raw) ? json_decode($raw, true) : (array) $raw;
            if (is_array($dbVal)) {
                $filtered = array_values(array_filter($dbVal, fn($p) => in_array($p, ['tencent', 'cloudeka'], true)));
                if (!empty($filtered)) {
                    return $filtered;
                }
            }
        }

        $name = strtolower($this->name);

        if ($this->isDatabasePackage()) {
            return ['tencent', 'cloudeka'];
        }

        if ($this->isAiPackage()) {
            return ['cloudeka', 'tencent'];
        }

        // Student Basic, Startup, and Business can ONLY use Cloudeka
        if (in_array($name, ['student basic', 'startup', 'business', 'ai production', 'ai production pro'], true) || str_contains($name, 'ai')) {
            return ['cloudeka'];
        }

        // Mahasiswa Basic, Standard, and Premium can ONLY use Tencent
        return ['tencent'];
    }

    public function isProviderAllowed(string $provider): bool
    {
        return in_array($provider, $this->allowedProviders(), true);
    }

    public function defaultProvider(): string
    {
        $dbDefault = $this->attributes['default_provider'] ?? null;
        if (!empty($dbDefault) && $this->isProviderAllowed($dbDefault)) {
            return $dbDefault;
        }

        return $this->allowedProviders()[0] ?? 'tencent';
    }

    public function getAllowedProvidersAttribute(): array
    {
        return $this->allowedProviders();
    }

    public function getDefaultProviderAttribute(): string
    {
        return $this->defaultProvider();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function replacementSpec()
    {
        return $this->belongsTo(self::class, 'replacement_spec_id');
    }

    public function legacyReplacedSpecs()
    {
        return $this->hasMany(self::class, 'replacement_spec_id');
    }

    public function getLifecycleStatusAttribute(): string
    {
        if ($this->is_active && $this->is_renewable) {
            return 'active';
        }
        if (!$this->is_active && $this->is_renewable) {
            return 'legacy';
        }
        if (!$this->is_active && !$this->is_renewable) {
            return 'discontinued';
        }
        return 'new_only';
    }

    public function getLifecycleLabelAttribute(): string
    {
        return match ($this->lifecycle_status) {
            'active' => 'Aktif (Katalog & Perpanjangan)',
            'legacy' => 'Legacy (Hanya Perpanjangan)',
            'discontinued' => 'Discontinued / EOL',
            default => 'Pendaftaran Baru Saja',
        };
    }

    public function canNewCheckout(): bool
    {
        return (bool) $this->is_active;
    }

    public function canRenew(): bool
    {
        return (bool) ($this->is_renewable || !empty($this->replacement_spec_id));
    }

    public function effectiveRenewalSpec(): self
    {
        if ($this->is_renewable) {
            return $this;
        }

        if ($this->replacement_spec_id && $this->relationLoaded('replacementSpec') && $this->replacementSpec) {
            return $this->replacementSpec->effectiveRenewalSpec();
        }

        if ($this->replacement_spec_id) {
            $replacement = self::find($this->replacement_spec_id);
            if ($replacement) {
                return $replacement->effectiveRenewalSpec();
            }
        }

        return $this;
    }

    public function effectiveRenewalPrice(): float
    {
        return (float) $this->effectiveRenewalSpec()->sell_price;
    }

    /**
     * PILAR 2: Margin Guardrail (Anti-Boncos Alert).
     * Mengecek apakah modal (cost_price) >= harga jual (sell_price).
     */
    public function hasDeficitMargin(): bool
    {
        return (float) $this->cost_price >= (float) $this->sell_price;
    }

    public function getIsDeficitMarginAttribute(): bool
    {
        return $this->hasDeficitMargin();
    }
}
