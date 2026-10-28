<?php

namespace App\Services;

use App\Models\VpsInstance;
use Illuminate\Support\Collection;

/**
 * PHASE 8 - Kecocokan masa aktif pelanggan dengan masa aktif di Supplier.
 *
 * Risiko yang dijaga: pelanggan sudah membayar sampai tanggal X, tetapi
 * pembelian di Supplier hanya menutup sampai tanggal sebelum X. Bila tidak
 * diperpanjang, VPS mati di Supplier padahal pelanggan masih berhak memakainya.
 */
class SupplierCoverageService
{
    public const STATUS_GAP = 'gap';
    public const STATUS_URGENT = 'urgent';
    public const STATUS_MISSING = 'missing';
    public const STATUS_COVERED = 'covered';

    /** Batas hari untuk menandai perpanjangan Supplier sebagai mendesak. */
    public const URGENT_DAYS = 3;

    /** Status instance yang masih harus hidup di Supplier. */
    public const LIVE_STATUSES = ['running', 'stopped', 'rebooting', 'reinstalling', 'suspended'];

    /**
     * Seluruh instance hidup beserta status kecocokannya, paling mendesak di atas.
     *
     * @return Collection<int, array>
     */
    public function rows(): Collection
    {
        return VpsInstance::with(['customer', 'supplierPurchases'])
            ->whereIn('status', self::LIVE_STATUSES)
            ->get()
            ->map(fn (VpsInstance $instance) => $this->rowFor($instance))
            ->sort(fn (array $a, array $b) => [
                $this->priority($a['status']),
                $a['supplier_expires_at']?->timestamp ?? 0,
            ] <=> [
                $this->priority($b['status']),
                $b['supplier_expires_at']?->timestamp ?? 0,
            ])
            ->values();
    }

    public function rowFor(VpsInstance $instance): array
    {
        $latest = $instance->supplierPurchases
            ->filter(fn ($p) => $p->supplier_expires_at !== null)
            ->sortByDesc('supplier_expires_at')
            ->first();

        $supplierExpires = $latest?->supplier_expires_at;
        $customerExpires = $instance->expires_at;

        $status = match (true) {
            $supplierExpires === null => self::STATUS_MISSING,
            $customerExpires !== null && $supplierExpires->lessThan($customerExpires)
                && $supplierExpires->lessThanOrEqualTo(now()->addDays(self::URGENT_DAYS)) => self::STATUS_URGENT,
            $customerExpires !== null && $supplierExpires->lessThan($customerExpires) => self::STATUS_GAP,
            default => self::STATUS_COVERED,
        };

        return [
            'instance' => $instance,
            'latest' => $latest,
            'supplier_expires_at' => $supplierExpires,
            'customer_expires_at' => $customerExpires,
            // Positif: Supplier menutup lebih lama. Negatif: ada celah.
            'drift_days' => ($supplierExpires && $customerExpires)
                ? (int) round($customerExpires->diffInDays($supplierExpires, false))
                : null,
            'status' => $status,
        ];
    }

    /**
     * Jumlah instance yang harus segera diperpanjang di Supplier.
     */
    public function urgentCount(): int
    {
        return $this->rows()->where('status', self::STATUS_URGENT)->count();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_URGENT => 'Perpanjang Segera',
            self::STATUS_GAP => 'Belum Tertutup',
            self::STATUS_MISSING => 'Tanpa Catatan',
            default => 'Tertutup',
        };
    }

    protected function priority(string $status): int
    {
        return match ($status) {
            self::STATUS_URGENT => 0,
            self::STATUS_MISSING => 1,
            self::STATUS_GAP => 2,
            default => 3,
        };
    }
}
