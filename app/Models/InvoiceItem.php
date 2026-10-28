<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Line item invoice. IMMUTABLE — hanya ditulis sekali saat generate,
 * tidak pernah di-UPDATE. Kalau ada koreksi, terbitkan invoice baru
 * (credit note) atau refund.
 *
 * Type: service | setup_fee | discount | tax | credit_apply | refund_adjustment | proration
 */
class InvoiceItem extends Model
{
    use HasFactory;

    public $timestamps = false; // hanya created_at (auto via default)

    protected $fillable = [
        'invoice_id', 'type', 'description', 'quantity', 'unit_price',
        'subtotal', 'tax_rate', 'tax_amount', 'total',
        'vps_spec_id', 'metadata', 'created_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function vpsSpec() { return $this->belongsTo(VpsSpec::class); }
}
