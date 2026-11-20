<?php

namespace App\Services\Provisioning\Exceptions;

use RuntimeException;

/**
 * Upstream quota pool exhausted for the requested region × bundle family.
 * Carries neutral, customer-safe alternatives (D4: hard-stop + explicit
 * region choice, never a silent auto-fallback). No upstream vendor names.
 */
class QuotaExhaustedException extends RuntimeException
{
    public function __construct(
        public readonly string $regionCode,
        public readonly string $bundleType,
        /** @var array<int, array{region_code: string, label: string, available: int}> */
        public readonly array $alternatives = [],
    ) {
        parent::__construct("Upstream quota exhausted [{$regionCode}/{$bundleType}].");
    }

    /** Neutral customer-facing message (white-label, no vendor names). */
    public function customerMessage(): string
    {
        $base = 'Kapasitas server di lokasi ini sedang penuh. Silakan pilih lokasi lain yang tersedia, atau coba lagi beberapa saat lagi.';

        if ($this->alternatives !== []) {
            $labels = implode(', ', array_column($this->alternatives, 'label'));
            $base .= " Lokasi alternatif yang masih tersedia: {$labels}.";
        }

        return $base;
    }
}