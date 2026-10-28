<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan pembelian layanan di supplier (Supplier).
 *
 * Dibuat di papan fulfillment (Phase 5), menjadi sumber biaya riil untuk
 * perhitungan margin (Phase 6), dan ditampilkan di Catatan Pembelian (Phase 8).
 *
 * Satu instance dapat memiliki beberapa baris: satu per periode pembelian,
 * karena pembelian di Supplier bersifat prepaid satu bulan.
 */
class SupplierPurchase extends Model
{
    protected $fillable = [
        'order_id',
        'vps_instance_id',
        'supplier',
        'supplier_order_no',
        'actual_cost',
        'purchased_at',
        'supplier_expires_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'actual_cost' => 'decimal:2',
        'purchased_at' => 'datetime',
        'supplier_expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vpsInstance()
    {
        return $this->belongsTo(VpsInstance::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
