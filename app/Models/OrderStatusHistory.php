<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail transisi status order. Ditulis oleh OrderStateMachine.
 * actor_type: system | admin | customer | webhook
 */
class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'reason',
        'actor_user_id',
        'actor_type',
        'ip_address',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
