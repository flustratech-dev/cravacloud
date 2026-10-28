<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SshKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'type', 'public_key', 'fingerprint', 'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    /**
     * Hitung SHA256 fingerprint dari public key string.
     * Format: "SHA256:base64(sha256(raw_key))"
     */
    public static function computeFingerprint(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey));
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid SSH public key format.');
        }
        $key = base64_decode($parts[1], true);
        if ($key === false) {
            throw new \InvalidArgumentException('Invalid base64 in SSH public key.');
        }
        $hash = base64_encode(hash('sha256', $key, true));
        return 'SHA256:' . rtrim($hash, '=');
    }
}
