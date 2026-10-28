<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'user_id', 'role_id', 'joined_at', 'invited_by',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function role() { return $this->belongsTo(Role::class); }
    public function invitedBy() { return $this->belongsTo(User::class, 'invited_by'); }
}
