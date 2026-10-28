<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            if (!$invoice->organization_id && $invoice->order_id) {
                $invoice->organization_id = Order::find($invoice->order_id)?->organization_id;
            }
        });
    }

    protected $fillable = [
        'order_id',
        'organization_id',
        'subscription_id',
        'tax_rate_id',
        'invoice_number',
        'amount',
        'subtotal',
        'discount_total',
        'tax_total',
        'credit_applied',
        'currency',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'period_start',
        'period_end',
        'is_renewal',
        'notes',
        'pdf_path',
        'paid_via_transaction_id',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'is_renewal' => 'boolean',
        'amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'credit_applied' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function paidViaTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'paid_via_transaction_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function couponRedemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
