<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan setiap upaya pembayaran, satu baris per transaksi gateway.
 * Immutable-friendly: raw_payload dan signature disimpan untuk audit forensik.
 *
 * Status: pending | authorized | settled | failed | expired | refunded | disputed
 */
class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_id',
        'provider',
        'provider_transaction_id',
        'provider_order_ref',
        'payment_method',
        'amount',
        'currency',
        'status',
        'fraud_status',
        'signature_key',
        'raw_payload',
        'failure_reason',
        'authorized_at',
        'settled_at',
        'failed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'amount' => 'decimal:2',
        'authorized_at' => 'datetime',
        'settled_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'expired'], true);
    }
}
