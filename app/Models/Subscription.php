<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Entitas langganan yang di-renew berulang. Terhubung ke satu VpsInstance
 * (nullable karena instance mungkin belum dibuat saat subscription baru).
 *
 * Status: active | past_due | grace_period | suspended | cancelled | terminated
 */
class Subscription extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $subscription) {
            if (!$subscription->organization_id && $subscription->customer_id) {
                $subscription->organization_id = User::find($subscription->customer_id)?->current_organization_id;
            }
        });
    }

    protected $fillable = [
        'customer_id', 'organization_id', 'vps_spec_id', 'vps_instance_id', 'order_id',
        'status', 'billing_cycle', 'unit_amount', 'currency', 'auto_renew',
        'current_period_start', 'current_period_end', 'next_billing_at',
        'grace_period_ends_at', 'cancelled_at', 'ended_at',
        'renewal_failures', 'last_renewal_attempt_at', 'last_renewal_error',
    ];

    protected $casts = [
        'unit_amount' => 'decimal:2',
        'auto_renew' => 'boolean',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'next_billing_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_renewal_attempt_at' => 'datetime',
        'renewal_failures' => 'integer',
    ];

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function vpsSpec() { return $this->belongsTo(VpsSpec::class); }
    public function vpsInstance() { return $this->belongsTo(VpsInstance::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function orders() { return $this->hasMany(Order::class); }

    /** Berapa hari dari sekarang sampai renew? Negatif kalau lewat. */
    public function daysUntilNextBilling(): ?int
    {
        return $this->next_billing_at ? (int) now()->diffInDays($this->next_billing_at, false) : null;
    }

    public function isDueSoon(int $days = 7): bool
    {
        $d = $this->daysUntilNextBilling();
        return $d !== null && $d >= 0 && $d <= $days;
    }

    public function isPastDue(): bool
    {
        return $this->next_billing_at && $this->next_billing_at->isPast() && $this->status === 'active';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['active', 'past_due', 'grace_period'], true);
    }
}
