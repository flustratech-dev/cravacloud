<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'value', 'max_discount', 'min_order_amount',
        'max_redemptions', 'max_per_customer', 'redeemed_count',
        'starts_at', 'ends_at', 'is_active', 'applies_to',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'applies_to' => 'array',
    ];

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Cek apakah coupon bisa dipakai oleh customer untuk order dengan subtotal tertentu.
     * Return array [ok=>bool, reason=>string|null].
     */
    public function canBeRedeemedBy(?User $customer, float $subtotal, ?int $vpsSpecId = null, ?string $cycle = null): array
    {
        if (!$this->is_active) return ['ok' => false, 'reason' => 'Kupon tidak aktif.'];
        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) return ['ok' => false, 'reason' => 'Kupon belum berlaku.'];
        if ($this->ends_at && $this->ends_at->isPast()) return ['ok' => false, 'reason' => 'Kupon sudah kadaluarsa.'];
        if ($this->min_order_amount && $subtotal < (float) $this->min_order_amount) {
            return ['ok' => false, 'reason' => 'Nilai order kurang dari minimum kupon.'];
        }
        if ($this->max_redemptions && $this->redeemed_count >= $this->max_redemptions) {
            return ['ok' => false, 'reason' => 'Kuota kupon habis.'];
        }
        if ($customer && $this->max_per_customer > 0) {
            $used = $this->redemptions()->where('customer_id', $customer->id)->count();
            if ($used >= $this->max_per_customer) return ['ok' => false, 'reason' => 'Anda sudah pernah pakai kupon ini.'];
        }
        $applies = $this->applies_to ?: [];
        if (!empty($applies['spec_ids']) && $vpsSpecId && !in_array($vpsSpecId, $applies['spec_ids'])) {
            return ['ok' => false, 'reason' => 'Kupon tidak berlaku untuk paket ini.'];
        }
        if (!empty($applies['cycles']) && $cycle && !in_array($cycle, $applies['cycles'])) {
            return ['ok' => false, 'reason' => 'Kupon tidak berlaku untuk siklus billing ini.'];
        }
        return ['ok' => true, 'reason' => null];
    }

    /** Hitung nominal diskon untuk subtotal tertentu. */
    public function calculateDiscount(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? round($subtotal * ((float) $this->value / 100), 2)
            : (float) $this->value;
        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }
        return min($discount, $subtotal); // tidak boleh > subtotal
    }
}
