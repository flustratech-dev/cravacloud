<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'order_id', 'payment_transaction_id', 'customer_id',
        'amount', 'method', 'status', 'reason', 'actor_user_id',
        'processed_at', 'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function paymentTransaction() { return $this->belongsTo(PaymentTransaction::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function creditTransactions() { return $this->hasMany(CreditTransaction::class); }
}
