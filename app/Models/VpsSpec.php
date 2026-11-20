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
        // Tencent integration (internal upstream mapping; never customer-facing).
        'upstream_bundle_id',
        'upstream_bundle_type',
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

    /**
     * True when this spec is mapped to a verified upstream bundle and is
     * therefore eligible for auto-provisioning (D3: only the 8 VPS packages;
     * ai_combo & managed_db stay unmapped/manual until repriced).
     */
    public function hasUpstreamMapping(): bool
    {
        return filled($this->upstream_bundle_id) && filled($this->upstream_bundle_type);
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
        return ['cravacloud'];
    }

    public function bandwidthQuotaLabel(): string
    {
        $features = is_array($this->features) ? $this->features : [];
        foreach ($features as $feat) {
            if (is_string($feat) && str_contains(strtolower($feat), 'tb')) {
                return $feat;
            }
        }

        return match (strtolower((string) $this->name)) {
            'starter xs' => '0,5 TB / bulan',
            'starter s' => '1,5 TB / bulan',
            'starter m' => '2,5 TB / bulan',
            'starter l' => '3,0 TB / bulan',
            'enterprise pro' => '5,0 TB / bulan',
            'enterprise max' => '6,0 TB / bulan',
            'enterprise ultra' => '7,0 TB / bulan',
            'enterprise extreme' => '8,0 TB / bulan',
            default => number_format(max(1, (int) $this->bandwidth) / 20, 1, ',', '.') . ' TB / bulan',
        };
    }

    public function isProviderAllowed(string $provider): bool
    {
        return in_array($provider, $this->allowedProviders(), true);
    }

    public function defaultProvider(): string
    {
        return 'cravacloud';
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
