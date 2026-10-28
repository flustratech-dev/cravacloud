<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * API key untuk programmatic access.
 * Format token: "cv_live_<prefix>.<secret>"
 *   - <prefix> disimpan plaintext (unique, index-able untuk lookup cepat)
 *   - <secret> hanya disimpan dalam bentuk hash (bcrypt-compatible)
 *
 * Ketika key digenerate, plaintext secret hanya ditampilkan SEKALI.
 */
class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'organization_id', 'name',
        'prefix', 'secret_hash', 'scopes',
        'last_used_at', 'last_used_ip',
        'expires_at', 'revoked_at', 'revoked_by',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function organization() { return $this->belongsTo(Organization::class); }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return !$this->isRevoked() && !$this->isExpired();
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
