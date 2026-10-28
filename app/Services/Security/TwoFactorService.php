<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * TOTP (RFC 6238) implementation — pure PHP tanpa library eksternal.
 *
 * Usage:
 *   $svc->generateSecret();                     // secret plaintext (base32)
 *   $svc->qrProvisioningUri($secret, $user);    // otpauth:// URI untuk QR code
 *   $svc->verifyCode($secret, $code);           // true/false
 *   $svc->enable($user, $secret);               // encrypt + simpan
 *   $svc->confirm($user, $code);                // set confirmed_at
 *   $svc->disable($user);
 *   $svc->generateRecoveryCodes();              // 8 codes plaintext
 *   $svc->consumeRecoveryCode($user, $code);
 *
 * Secret & recovery codes disimpan encrypted (Laravel Crypt) di kolom
 * users.two_factor_secret / two_factor_recovery_codes.
 */
class TwoFactorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const CODE_LENGTH = 6;
    private const PERIOD = 30;      // 30 detik per code
    private const ALGORITHM = 'sha1';
    private const WINDOW = 1;       // tolerate ±1 period untuk clock skew

    /** Generate secret base32 (20 bytes = 160 bits = sesuai standar TOTP). */
    public function generateSecret(int $length = 20): string
    {
        $bytes = random_bytes($length);
        return $this->base32Encode($bytes);
    }

    /** Verify code TOTP (dengan window). */
    public function verifyCode(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;

        $secretBytes = $this->base32Decode($secret);
        if ($secretBytes === false) return false;

        $timestamp = $timestamp ?? time();
        $timeSlice = intdiv($timestamp, self::PERIOD);

        // Compare pakai hash_equals untuk timing safety, cek window ±.
        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $expected = $this->generateCodeForTimeSlice($secretBytes, $timeSlice + $offset);
            if (hash_equals($expected, $code)) return true;
        }
        return false;
    }

    /** Buat otpauth:// URI untuk QR code (Google Authenticator, Authy, dst). */
    public function qrProvisioningUri(string $secret, User $user, string $issuer = 'CravaCloud'): string
    {
        $label = rawurlencode("{$issuer}:{$user->email}");
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::CODE_LENGTH,
            'period' => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** Enable 2FA: simpan secret encrypted. Belum confirmed. */
    public function enable(User $user, string $secret): void
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** Confirm 2FA setelah user berhasil verify code pertama kali. */
    public function confirm(User $user, string $code): bool
    {
        if (!$user->two_factor_secret) return false;
        $secret = Crypt::decryptString($user->two_factor_secret);
        if (!$this->verifyCode($secret, $code)) return false;

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        return true;
    }

    /** Disable 2FA. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** Cek code (untuk login flow — dipanggil setelah primary auth). */
    public function verifyForUser(User $user, string $code): bool
    {
        if (!$user->two_factor_secret || !$user->two_factor_confirmed_at) return false;
        $secret = Crypt::decryptString($user->two_factor_secret);
        return $this->verifyCode($secret, $code);
    }

    /** Generate 8 recovery codes format XXXX-XXXX. */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4));
        }
        return $codes;
    }

    /** Get plaintext recovery codes (decrypt). */
    public function getRecoveryCodes(User $user): array
    {
        if (!$user->two_factor_recovery_codes) return [];
        return json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?: [];
    }

    /** Pakai (consume) 1 recovery code. Return true kalau match. */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->getRecoveryCodes($user);
        $code = Str::upper(trim($code));
        $idx = array_search($code, $codes, true);
        if ($idx === false) return false;

        unset($codes[$idx]);
        $codes = array_values($codes);
        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
        ])->save();
        return true;
    }

    // ============================================================
    // Internal helpers
    // ============================================================

    private function generateCodeForTimeSlice(string $secretBytes, int $timeSlice): string
    {
        // Pack timeSlice as 8-byte big-endian.
        $time = str_pad(pack('N*', 0, $timeSlice), 8, "\0", STR_PAD_LEFT);
        $hash = hash_hmac(self::ALGORITHM, $time, $secretBytes, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $code = $binary % (10 ** self::CODE_LENGTH);
        return str_pad((string) $code, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= self::BASE32_ALPHABET[bindec($chunk)];
        }
        // Optional padding untuk multiple of 8. Kita skip pad karena banyak
        // authenticator app tidak strict soal ini.
        return $out;
    }

    private function base32Decode(string $input): string|false
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
        if ($input === '') return false;

        $bits = '';
        foreach (str_split($input) as $c) {
            $pos = strpos(self::BASE32_ALPHABET, $c);
            if ($pos === false) return false;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
        }
        return $out;
    }
}
