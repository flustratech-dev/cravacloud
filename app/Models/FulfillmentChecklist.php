<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PHASE 5 - Satu butir daftar periksa setup pada sebuah order.
 *
 * Butir yang sudah dicentang tersimpan, sehingga pekerjaan yang terputus
 * dapat dilanjutkan tanpa mengingat langkah mana yang sudah selesai.
 */
class FulfillmentChecklist extends Model
{
    protected $fillable = [
        'order_id',
        'step_key',
        'is_done',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'is_done' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
