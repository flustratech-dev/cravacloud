<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bukti persetujuan ketentuan layanan saat checkout (clickwrap).
 *
 * Baris di tabel ini adalah alat bukti. Jangan pernah diubah setelah dibuat,
 * dan jangan dihapus selama pesanan terkaitnya masih ada.
 */
class TermsAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'terms_version',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
