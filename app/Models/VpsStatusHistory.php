<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail transisi status VPS instance. Ditulis oleh VpsStateMachine.
 */
class VpsStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'vps_status_history';
    public $timestamps = false;

    protected $fillable = [
        'vps_instance_id',
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

    public function vpsInstance()
    {
        return $this->belongsTo(VpsInstance::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
