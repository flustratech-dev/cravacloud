<?php

namespace App\Services\Security;

use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * API Key management.
 *
 * Format token yang dikirim ke user: "cv_live_<prefix>.<secret>"
 *   - <prefix>: 16 chars, disimpan plaintext untuk lookup cepat
 *   - <secret>: 40 chars, disimpan hanya sebagai hash
 *
 * User HANYA melihat full token satu kali (saat mint). Setelahnya
 * hanya prefix yang bisa di-lookup untuk audit/revoke.
 */
class ApiKeyService
{
    public const TOKEN_PREFIX = 'cv_live_';

    /**
     * Buat API key baru. Return array ['api_key' => ApiKey, 'plain_token' => string].
     * plain_token HANYA tersedia di response ini — tidak bisa direcover.
     */
    public function mint(
        User $user,
        string $name,
        array $scopes = [],
        ?Organization $organization = null,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        do {
            $prefix = Str::lower(Str::random(16));
        } while (ApiKey::where('prefix', $prefix)->exists());

        $secret = Str::random(40);
        $plainToken = self::TOKEN_PREFIX . $prefix . '.' . $secret;

        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'organization_id' => $organization?->id,
            'name' => $name,
            'prefix' => $prefix,
            'secret_hash' => Hash::make($secret),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        return ['api_key' => $apiKey, 'plain_token' => $plainToken];
    }

    /**
     * Verifikasi token dari header Authorization. Return ApiKey kalau valid.
     */
    public function verify(string $token): ?ApiKey
    {
        if (!str_starts_with($token, self::TOKEN_PREFIX)) return null;
        $body = substr($token, strlen(self::TOKEN_PREFIX));
        if (!str_contains($body, '.')) return null;
        [$prefix, $secret] = explode('.', $body, 2);
        if (strlen($prefix) !== 16 || strlen($secret) !== 40) return null;

        $key = ApiKey::where('prefix', $prefix)->first();
        if (!$key || !$key->isActive()) return null;
        if (!Hash::check($secret, $key->secret_hash)) return null;

        return $key;
    }

    /** Rotasi: revoke lama, mint baru dengan config identik. */
    public function rotate(ApiKey $key, User $actor): array
    {
        $this->revoke($key, $actor);
        return $this->mint(
            $key->user,
            $key->name . ' (rotated)',
            $key->scopes ?? [],
            $key->organization,
            $key->expires_at
        );
    }

    public function revoke(ApiKey $key, User $actor): void
    {
        if ($key->revoked_at) return;
        $key->update([
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
        ]);
    }

    /** Update last_used_at + last_used_ip (dipanggil dari middleware). */
    public function touch(ApiKey $key, ?string $ip = null): void
    {
        $key->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->save();
    }
}
