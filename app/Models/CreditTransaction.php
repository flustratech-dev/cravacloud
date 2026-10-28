<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ledger saldo credit pelanggan. Balance current = sum(amount) untuk customer,
 * atau ambil `balance_after` dari row terakhir (untuk audit).
 */
class CreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'type', 'amount', 'balance_after', 'reason',
        'order_id', 'invoice_id', 'refund_id', 'actor_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function refund() { return $this->belongsTo(Refund::class); }

    /** Saldo terkini untuk seorang customer. */
    public static function balanceFor(int $customerId): float
    {
        return (float) static::where('customer_id', $customerId)->sum('amount');
    }
}
