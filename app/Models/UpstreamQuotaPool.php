<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Upstream quota pool — one row per region × bundle family.
 *
 * Tencent Lighthouse caps instances per bundle type per region (verified:
 * 20 STARTER_BUNDLE + 20 GENERAL_BUNDLE in ap-jakarta).
 *
 * reserved: hard reservations taken when an order becomes `paid`
 *           (revision 1: pending checkouts never hold slots).
 * used:     live upstream instances, corrected by syncFromProvider().
 *
 * Mutations MUST go through App\Services\UpstreamQuotaService (atomic
 * conditional UPDATE) — never increment these columns ad-hoc.
 */
class UpstreamQuotaPool extends Model
{
    protected $fillable = [
        'region_code',
        'bundle_type',
        'capacity',
        'reserved',
        'used',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'reserved' => 'integer',
        'used' => 'integer',
    ];

    public function availableSlots(): int
    {
        return max(0, $this->capacity - $this->reserved - $this->used);
    }

    public function usagePercent(): float
    {
        if ($this->capacity <= 0) {
            return 0.0;
        }

        return round(($this->reserved + $this->used) / $this->capacity * 100, 1);
    }
}