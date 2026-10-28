<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id', 'action', 'subject_type', 'subject_id',
        'description', 'changes', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function admin() { return $this->belongsTo(User::class, 'admin_user_id'); }

    public function subject()
    {
        return $this->morphTo();
    }
}
