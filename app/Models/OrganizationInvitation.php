<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrganizationInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'email', 'role_id', 'token',
        'invited_by', 'expires_at', 'accepted_at', 'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(64);
            }
            if (empty($invite->expires_at)) {
                $invite->expires_at = now()->addDays(7);
            }
        });
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function role() { return $this->belongsTo(Role::class); }
    public function inviter() { return $this->belongsTo(User::class, 'invited_by'); }

    public function isValid(): bool
    {
        return !$this->accepted_at
            && !$this->revoked_at
            && (!$this->expires_at || $this->expires_at->isFuture());
    }
}
