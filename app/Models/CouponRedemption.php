<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id', 'order_id', 'invoice_id', 'customer_id', 'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
}
